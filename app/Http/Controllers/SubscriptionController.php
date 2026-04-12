<?php

namespace App\Http\Controllers;

use NotchPay\Payment;
use NotchPay\NotchPay;
use Illuminate\Http\Request;
use App\Models\PremiumTransaction;
use Illuminate\Support\Facades\Log;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class SubscriptionController extends Controller
{
    public function __construct()
    {
        NotchPay::setApiKey(env('NOTCHPAY_API_KEY'));
        NotchPay::setPrivateKey(env('NOTCHPAY_PRIVATE_KEY'));
    }

   
    /**
     * Initialise une demande d'abonnement Premium via NotchPay
     */
    public function upgradeToPremium(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Utilisateur non authentifié'], 401);
        }

        $validated = $request->validate([
            'subscription_type' => 'required|in:monthly,yearly',
            'email' => 'required|email',
        ]);

        $subscriptionType = $validated['subscription_type'];
        $email = $validated['email'];
        $amount = $subscriptionType === 'monthly' ? 50 : 50000;
        $typeAbonnement = $subscriptionType === 'monthly' ? 'mensuel' : 'annuel';

        try {
            $payment = Payment::initialize([
                'amount' => $amount,
                'email' => $email,
                'currency' => 'XAF',
                'reference' => 'PREMIUM_' . $user->id . '_' . time(),
                'callback' => route('subscription.callback'),
                'description' => 'Abonnement Premium ' . $typeAbonnement,
                'metadata' => [
                    'user_id' => $user->id,
                    'subscription_type' => $subscriptionType,
                    'customer_id' => (string) $user->id,
                    'order_id' => 'ORDER_' . $user->id . '_' . time()
                ],
                'channels' => ['mobile_money', 'card'],
            ]);

            // Enregistrer en attente
            PremiumTransaction::create([
                'user_id' => $user->id,
                'type_abonnement' => $typeAbonnement,
                'montant' => $amount,
                'methode_paiement' => 'notchpay',
                'transaction_id_notchpay' => $payment->transaction->reference,
                'statut' => 'en_attente',
                'date_transaction' => now(),
            ]);

            // 🔥 Retourner l'URL de redirection pour le frontend
            return response()->json([
                'redirect_url' => $payment->authorization_url,
                'reference' => $payment->transaction->reference,
                'message' => 'Paiement initialisé avec succès'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de l\'initialisation du paiement',
                'error' => $e->getMessage()
            ], 400);
        }
    }
    /**
     * CALLBACK - Gestion du retour de NotchPay
     */
    /**
     * Gère le retour de NotchPay après une tentative de paiement (activation premium si succès)
     */
    public function handleCallback(Request $request)
    {
        $reference = $request->query('reference');
        $frontendUrl = env('FRONTEND_URL');
        
        if (!$reference) {
            // Rediriger vers le frontend avec erreur
            return redirect($frontendUrl . '/profil?payment=error&message=reference_missing');
        }

        try {
             $payment = Payment::verify($reference);

            $premiumTransaction = PremiumTransaction::where('transaction_id_notchpay', $reference)->first();
            
            if (!$premiumTransaction) {
                return redirect($frontendUrl . '/profil?payment=error&message=transaction_not_found&reference=' . $reference);
            }

            $user = $premiumTransaction->user;

            if (in_array( $payment->transaction->status, ['complete', 'success', 'completed'])) {
                // ✅ PAIEMENT RÉUSSI
                $premiumTransaction->update([
                    'statut' => 'complete',
                    'date_transaction' => now(),
                ]);


                // Activer premium
                $subscriptionType = $premiumTransaction->type_abonnement === 'mensuel' ? 'monthly' : 'yearly';
                $endsAt = $subscriptionType === 'monthly' ? now()->addMonth() : now()->addYear();
                
                $user->update([
                    'premium' => 1,
                    'subscription_ends_at' => $endsAt,
                ]);

                // Rediriger vers le frontend avec succès
                return redirect($frontendUrl . '/profil?payment=success&premium=activated&reference=' . $reference);

            } else {
                // ❌ PAIEMENT ÉCHOUÉ
                $premiumTransaction->update([
                    'statut' =>  $payment->transaction->status,
                ]);

                return redirect($frontendUrl . '/profil?payment='. $payment->transaction->status.'&reference=' . $reference . '&status=' .  $payment->transaction->status);
            }

        } catch (\Exception $e) {
            \Log::error('Erreur callback NotchPay:', [
                'reference' => $reference,
                'error' => $e->getMessage(),
                'status'=> $payment->transaction->status ?? 'unknown'
            ]);

            return redirect($frontendUrl . '/profil?payment=error&message=processing_error&reference=' . $reference);
        }
    }

       /**
     * Récupérer la transaction premium en attente de l'utilisateur (une seule max)
     */
    /**
     * Récupère la transaction premium actuellement en attente pour l'utilisateur
     */
    public function getPendingTransaction(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'error' => 'Utilisateur non authentifié'
                ], 401);
            }

            // Récupérer la seule transaction en attente (s'il y en a une)
            $pendingTransaction = PremiumTransaction::where('user_id', $user->id)
                ->where('statut', 'en_attente')
                ->orderBy('created_at', 'desc')
                ->first();

            return response()->json([
                'data' => $pendingTransaction,
                'has_pending' => !is_null($pendingTransaction)
            ], 200);

        } catch (\Exception $e) {
            Log::error('Erreur récupération transaction premium en attente:', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Erreur lors de la récupération de la transaction'
            ], 500);

            
        }
    }

    /**
     * Vérifier le statut d'une transaction premium
     */
    /**
     * Vérifie et synchronise le statut d'une transaction spécifique avec NotchPay
     */
    public function checkTransactionStatus($id)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'error' => 'Utilisateur non authentifié'
                ], 401);
            }

            // Trouver la transaction
            $transaction = PremiumTransaction::where('id', $id)
                ->where('user_id', $user->id)
                ->first();
// $oldStatut= null;
                
                if (!$transaction) {
                    return response()->json([
                    'error' => 'Transaction non trouvée'
                ], 404);
            }else{
                
                $oldStatut = $transaction->status;
            }

            // Vérifier le statut avec NotchPay
            $payment = Payment::verify($transaction->transaction_id_notchpay);
            $notchpayStatus = $payment->transaction->status;

            // Mapper le statut
            $localStatus = $this->mapNotchPayStatus($notchpayStatus);

            // Si le statut a changé
            if ($transaction->statut !== $localStatus) {
                $transaction->update([
                    'statut' => $localStatus,
                    'notchpay_metadata' => array_merge(
                        $transaction->notchpay_metadata ?? [],
                        [
                            'last_status_check' => now()->toISOString(),
                            'notchpay_status' => $notchpayStatus,
                            'notchpay_response' => $payment->transaction
                        ]
                    )
                ]);

                // Si le paiement est maintenant réussi, activer Premium
                if ($localStatus === 'complete' 
                && $oldStatut !== 'complete'
                ) {
                    $this->activatePremium($transaction);
                }
            }

            return response()->json([
                'transaction' => $transaction,
                'statut' => $localStatus,
                'notchpay_status' => $notchpayStatus,
                'message' => 'Statut vérifié avec succès'
            ], 200);

        } catch (\NotchPay\Exceptions\ApiException $e) {
            Log::error('Erreur API NotchPay vérification statut:', [
                'transaction_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Erreur de connexion avec le service de paiement',
                'transaction' => $transaction ?? null
            ], 400);

        } catch (\Exception $e) {
            Log::error('Erreur vérification statut transaction premium:', [
                'transaction_id' => $id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Erreur lors de la vérification du statut'
            ], 500);
        }
    }

    /**
     * Activer l'abonnement Premium après paiement réussi
     */
    private function activatePremium(PremiumTransaction $transaction)
    {
        try {
            $user = $transaction->user;


            // Activer premium
            $subscriptionType = $transaction->type_abonnement === 'mensuel' ? 'monthly' : 'yearly';
            $endsAt = $subscriptionType === 'monthly' ? now()->addMonth() : now()->addYear();
            
            $user->update([
                'premium' => true,
                'subscription_ends_at' => $endsAt,
            ]);

            // Mettre à jour la date de transaction
            $transaction->update([
                'date_transaction' => now(),
            ]);

            Log::info('Abonnement Premium activé avec succès', [
                'transaction_id' => $transaction->id,
                'user_id' => $user->id,
                'subscription_type' => $subscriptionType,
                'jetons_bonus' => $jetonsBonus
            ]);

        } catch (\Exception $e) {
            Log::error('Erreur activation Premium:', [
                'transaction_id' => $transaction->id,
                'user_id' => $transaction->user_id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Mapper les statuts NotchPay
     */
    private function mapNotchPayStatus($notchpayStatus)
    {
        $status = strtolower($notchpayStatus);
        
        return match($status) {
            'complete', 'success', 'completed' => 'complete',
            'failed', 'failure' => 'failed',
            'canceled', 'cancelled' => 'canceled',
            'expired' => 'expired',
            'pending', 'processing' => 'en_attente',
            default => 'en_attente'
        };
    }
}