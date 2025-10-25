<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Models\PremiumTransaction;

class WebhookController extends Controller
{
    /**
     * Traitement des webhooks NotchPay
     */
    public function handle(Request $request)
    {
        // 1. Récupérer la payload et la signature
        $payload = $request->getContent();
        $signature = $request->header('x-notchpay-signature');
        
        // 2. Journaliser pour le débogage
        Log::channel('webhooks')->info('Webhook reçu', [
            'payload' => $payload,
            'signature' => $signature,
            'ip' => $request->ip()
        ]);

        // 3. Vérifier la signature (IMPORTANT pour la sécurité)
        if (!$this->verifySignature($payload, $signature)) {
            Log::channel('security')->warning('Signature webhook invalide', [
                'ip' => $request->ip(),
                'signature_received' => $signature
            ]);
            return response()->json(['error' => 'Signature invalide'], 401);
        }

        // 4. Parser les données
        $data = json_decode($payload, true);
        
        // 5. Traiter l'événement
        try {
            $this->processEvent($data);
            return response()->json(['status' => 'success']);
            
        } catch (\Exception $e) {
            Log::channel('webhooks')->error('Erreur traitement webhook', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            return response()->json(['error' => 'Erreur de traitement'], 500);
        }
    }

    /**
     * Vérification de la signature du webhook
     */
    private function verifySignature($payload, $signature): bool
    {
        $secret = env('NOTCHPAY_WEBHOOK_SECRET');
        
        if (!$secret) {
            Log::channel('webhooks')->warning('Secret webhook non configuré');
            return false;
        }

        $computedSignature = hash_hmac('sha256', $payload, $secret);
        
        return hash_equals($computedSignature, $signature);
    }

    /**
     * Traitement des différents types d'événements
     */
    private function processEvent(array $data)
    {
        $eventType = $data['event'] ?? 'unknown';
        $eventData = $data['data'] ?? [];

        Log::channel('webhooks')->info("Événement reçu: {$eventType}", $eventData);

        switch ($eventType) {
            case 'payment.complete':
                $this->handlePaymentComplete($eventData);
                break;
                
            case 'payment.failed':
                $this->handlePaymentFailed($eventData);
                break;
                
            case 'payment.pending':
                $this->handlePaymentPending($eventData);
                break;
                
            default:
                Log::channel('webhooks')->warning('Événement non géré', [
                    'event' => $eventType,
                    'data' => $eventData
                ]);
                break;
        }
    }

    /**
     * Traitement d'un paiement réussi
     */
    private function handlePaymentComplete(array $paymentData)
    {
        $reference = $paymentData['reference'] ?? null;
        
        if (!$reference) {
            throw new \Exception('Reference manquante dans les données de paiement');
        }

        // Trouver la transaction
        $transaction = PremiumTransaction::where('transaction_id_mesomb', $reference)->first();
        
        if (!$transaction) {
            Log::channel('webhooks')->warning('Transaction non trouvée', ['reference' => $reference]);
            return;
        }

        // Vérifier si déjà traitée
        if ($transaction->statut === 'réussi') {
            Log::channel('webhooks')->info('Transaction déjà traitée', ['reference' => $reference]);
            return;
        }

        // Mettre à jour la transaction
        $transaction->update([
            'statut' => 'réussi',
            'date_transaction' => now(),
        ]);

        // Mettre à jour l'utilisateur
        $user = $transaction->user;
        $user->jetons += 30;
        
        // Activer l'abonnement premium
        $subscriptionType = $transaction->type_abonnement === 'mensuel' ? 'monthly' : 'yearly';
        $endsAt = $subscriptionType === 'monthly' ? now()->addMonth() : now()->addYear();
        
        $user->update([
            'premium' => 1,
            'subscription_ends_at' => $endsAt,
        ]);

        Log::channel('webhooks')->info('Paiement traité avec succès', [
            'user_id' => $user->id,
            'reference' => $reference,
            'jetons_ajoutes' => 30
        ]);

        // Ici vous pouvez aussi :
        // - Envoyer un email de confirmation
        // - Notifier l'administration
        // - Déclencher d'autres actions métier
    }

    /**
     * Traitement d'un paiement échoué
     */
    private function handlePaymentFailed(array $paymentData)
    {
        $reference = $paymentData['reference'] ?? null;
        
        if (!$reference) return;

        $transaction = PremiumTransaction::where('transaction_id_mesomb', $reference)->first();
        
        if ($transaction && $transaction->statut === 'en_attente') {
            $transaction->update([
                'statut' => 'echec',
                'error_message' => $paymentData['message'] ?? 'Paiement échoué',
            ]);

            Log::channel('webhooks')->info('Paiement marqué comme échoué', ['reference' => $reference]);
        }
    }

    /**
     * Traitement d'un paiement en attente
     */
    private function handlePaymentPending(array $paymentData)
    {
        $reference = $paymentData['reference'] ?? null;
        
        if (!$reference) return;

        Log::channel('webhooks')->info('Paiement en attente', ['reference' => $reference]);
        
        // Généralement pas besoin de modifier le statut qui est déjà "en_attente"
        // Mais vous pouvez logger l'événement
    }
}