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
            'email' => 'email',
            'name' => 'string',
            'phone_number' => 'nullable|regex:/^6[0-9]{8}$/',
        ]);

        $subscriptionType = $validated['subscription_type'];
        $email = $validated['email']??'mciagnessi@gmail.com';
        $name = $validated['name']??'dominique';
        $phoneNumber = $validated['phone_number'] ?? null;
        
        $amount = $subscriptionType === 'monthly' ? 1000 : 50000;
        $typeAbonnement = $subscriptionType === 'monthly' ? 'mensuel' : 'annuel';

        try {
            $payment = Payment::initialize([
                'amount' => $amount,
                'currency' => 'XAF',
                'email' => $email,
                'name' => $name,
                'phone' => $phoneNumber,
                'reference' => 'PREMIUM_' . $user->id . '_' . time(),
                'callback' => route('subscription.callback'), // URL de retour
                'description' => 'Abonnement Premium ' . $typeAbonnement,
                'metadata' => [
                    'user_id' => $user->id,
                    'subscription_type' => $subscriptionType,
                ]
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

            return response()->json([
                'message' => 'Paiement initialisé avec succès',
                'authorization_url' => $payment->authorization_url,
                'reference' => $payment->transaction->reference,
                'payment' => $payment,
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Erreur lors de l\'initialisation du paiement',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * CALLBACK SEUL - Suffisant pour la plupart des cas
     */
    public function handleCallback(Request $request)
    {
        $reference = $request->query('reference');
        
        if (!$reference) {
            // return view('payment.error', ['message' => 'Référence manquante']);
            return response()->json([
                'message' => 'fallback reference',
                'reference' => $reference,
            ], 400);
        }

        try {
            // Vérifier IMMÉDIATEMENT le statut avec l'API NotchPay
            $transaction =  Payment::verify($reference);

            $premiumTransaction = PremiumTransaction::where('transaction_id_mesomb', $reference)->first();
            
            if (!$premiumTransaction) {
                         return response()->json([
                'message' => 'Transaction non trouvée',
                'reference' => $reference,
            ], 400);
                // return view('payment.error', ['message' => 'Transaction non trouvée']);
            }

            $user = $premiumTransaction->user;

            // Vérifier le statut
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

                
  return response()->json([
                'message' => 'payment.success',
                'reference' => $reference,
                // return view('payment.success', [
                //     'payment' => $transaction,
                //     'user' => $user
                ]);

            } else {
                // ❌ PAIEMENT ÉCHOUÉ
                $premiumTransaction->update([
                    'statut' => 'echec',
                ]);
                  return response()->json([
                'message' => 'Paiement non complété',
                'reference' => $reference,

                // return view('payment.failed', [
                //     'payment' => $transaction,
                //     'message' => 'Paiement non complété'
                ]);
            }

        } catch (\Exception $e) {
              return response()->json([
                'message' => 'error de paiement',
                'reference' => $reference,
            // return view('payment.error', [
            //     'message' => 'Erreur: ' . $e->getMessage()
            ]);
        }
    }
}