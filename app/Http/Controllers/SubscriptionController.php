<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use NotchPay\NotchPay;
use NotchPay\Payment;
use App\Models\PremiumTransaction;

class SubscriptionController extends Controller
{
    public function __construct()
    {
        NotchPay::setApiKey(env('NOTCHPAY_API_KEY'));
        NotchPay::setPrivateKey(env('NOTCHPAY_PRIVATE_KEY'));
    }

   
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
        $amount = $subscriptionType === 'monthly' ? 1000 : 50000;
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
                'transaction_id_mesomb' => $payment->transaction->reference,
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
    public function handleCallback(Request $request)
    {
        $reference = $request->query('reference');
        $frontendUrl = env('FRONTEND_URL');
        
        if (!$reference) {
            // Rediriger vers le frontend avec erreur
            return redirect($frontendUrl . 'profil?payment=error&message=reference_missing');
        }

        try {
            $transaction = Payment::verify($reference);

            $premiumTransaction = PremiumTransaction::where('transaction_id_mesomb', $reference)->first();
            
            if (!$premiumTransaction) {
                return redirect($frontendUrl . 'profil?payment=error&message=transaction_not_found&reference=' . $reference);
            }

            $user = $premiumTransaction->user;

            if (in_array($transaction->status, ['complete', 'success', 'completed'])) {
                // ✅ PAIEMENT RÉUSSI
                $premiumTransaction->update([
                    'statut' => 'réussi',
                    'date_transaction' => now(),
                ]);

                // Ajouter les jetons
                $user->jetons += 30;
                $user->save();

                // Activer premium
                $subscriptionType = $premiumTransaction->type_abonnement === 'mensuel' ? 'monthly' : 'yearly';
                $endsAt = $subscriptionType === 'monthly' ? now()->addMonth() : now()->addYear();
                
                $user->update([
                    'premium' => 1,
                    'subscription_ends_at' => $endsAt,
                ]);

                // Rediriger vers le frontend avec succès
                return redirect($frontendUrl . '/premium?payment=success&premium=activated&reference=' . $reference);

            } else {
                // ❌ PAIEMENT ÉCHOUÉ
                $premiumTransaction->update([
                    'statut' => 'echec',
                ]);

                return redirect($frontendUrl . 'profil?payment=failed&reference=' . $reference . '&status=' . $transaction->status);
            }

        } catch (\Exception $e) {
            \Log::error('Erreur callback NotchPay:', [
                'reference' => $reference,
                'error' => $e->getMessage()
            ]);

            return redirect($frontendUrl . 'profil?payment=error&message=processing_error&reference=' . $reference);
        }
    }
}