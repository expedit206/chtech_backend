<?php

namespace App\Http\Controllers;

use App\Models\JetonOffer;
use App\Models\JetonTransaction;
use Illuminate\Http\Request;
use NotchPay\NotchPay;
use NotchPay\Payment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class JetonController extends Controller
{
    public function __construct()
    {
        NotchPay::setApiKey(env('NOTCHPAY_API_KEY'));
    }

    /**
     * Liste des offres du marché
     */
    public function index(Request $request)
    {
        $query = JetonOffer::with('user')
            ->where('statut', 'disponible');

        if ($request->has('quantity_min')) {
            $query->where('nombre_jetons', '>=', $request->quantity_min);
        }

        $perPage = 10;
        $offers = $query->inRandomOrder()->paginate($perPage);

        return response()->json([
            'data' => $offers->items(),
            'current_page' => $offers->currentPage(),
            'last_page' => $offers->lastPage(),
            'total' => $offers->total(),
        ], 200);
    }

    /**
     * Achat sur le marché
     */
    public function buy($offer_id, Request $request)
    {
        $acheteur = Auth::user();

        if (!$acheteur) {
            return response()->json(['message' => 'Utilisateur non authentifié'], 401);
        }

        // Récupérer l'offre
        $offer = JetonOffer::with('user')->findOrFail($offer_id);

        if ($offer->statut !== 'disponible') {
            return response()->json(['message' => 'Offre non disponible'], 400);
        }

        // Calcul des montants
        $commission = $offer->total_prix * 0.10;
        $montantNet = $offer->total_prix - $commission;

        // Validation
        $validated = $request->validate([
            'email' => 'required|email',
            'name' => 'required|string',
        ]);

        DB::beginTransaction();
        try {
            // Créer la transaction
            $transaction = JetonTransaction::create([
                'acheteur_id' => $acheteur->id,
                'vendeur_id' => $offer->user_id,
                'offer_id' => $offer->id,
                'type' => 'marketplace',
                'nombre_jetons' => $offer->nombre_jetons,
                'prix_unitaire' => $offer->prix_unitaire,
                'montant_total' => $offer->total_prix,
                'commission_plateforme' => $commission,
                'montant_net_vendeur' => $montantNet,
                'statut' => 'pending',
                'notchpay_reference' => '',

            ]);

            // Initialiser le paiement NotchPay
            $payment = Payment::initialize([
                'amount' => 50,
                // 'amount' => $offer->total_prix,
                'email' => 'mciagnessi@gmail.com',
                'currency' => 'XAF',
                'reference' => 'JETON_MKT_' . $transaction->id . '_' . time(),
                'callback' => route('jeton.callback'), // Seulement le callback
                'description' => 'Achat de ' . $offer->nombre_jetons . ' jetons ESPACE - Marketplace',
                'metadata' => [
                    'transaction_id' => $transaction->id,
                    'type' => 'marketplace',
                    'acheteur_id' => $acheteur->id,
                    'vendeur_id' => $offer->user_id,
                    'offer_id' => $offer->id,
                ],
                'channels' => ['mobile_money', 'card'],
            ]);

                    $transaction->offer->update(['statut' => 'en cours']);

            // Mettre à jour avec la référence NotchPay
            $transaction->update([
                'notchpay_reference' => $payment->transaction->reference,
            ]);

            DB::commit();

            return response()->json([
                'redirect_url' => $payment->authorization_url,
                'reference' => $payment->transaction->reference,
                'transaction_id' => $transaction->id,
                'message' => 'Paiement initialisé avec succès'
            ], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Erreur achat marketplace:', [
                'offer_id' => $offer_id,
                'user_id' => $acheteur->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Erreur lors de l\'initialisation du paiement',
                'error' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Achat direct depuis la plateforme
     */
    public function purchaseFromPlatform(Request $request)
    {
        $user = Auth::user();
        
        $validated = $request->validate([
            'jeton_quantity' => 'required|integer|min:1',
            'email' => 'required|email',
            'phone' => 'required|string'
        ]);

        // Prix fixe plateforme
        $prixUnitaire = 5; // 10 XAF par jeton
        $montantTotal = $validated['jeton_quantity'] * $prixUnitaire;

        DB::beginTransaction();
        try {
            $transaction = JetonTransaction::create([
                'acheteur_id' => $user->id,
                'vendeur_id' => null, // La plateforme
                'type' => 'platform',
                'nombre_jetons' => $validated['jeton_quantity'],
                'prix_unitaire' => $prixUnitaire,
                'montant_total' => $montantTotal,
                'commission_plateforme' => 0,
                'montant_net_vendeur' => 0,
                'statut' => 'pending',
                'notchpay_reference' => '',
            ]);

            $payment = Payment::initialize([
                'amount' => 50,
                'email' => $validated['email'],
                'currency' => 'XAF',
                'reference' => 'JETON_PLAT_' . $transaction->id . '_' . time(),
                'callback' => route('jeton.callback'),
                'description' => 'Achat direct de ' . $validated['jeton_quantity'] . ' jetons ESPACE',
                'metadata' => [
                    'transaction_id' => $transaction->id,
                    'type' => 'platform',
                    'user_id' => $user->id,
                ],
                'channels' => ['mobile_money', 'card'],
            ]);

            $transaction->update([
                'notchpay_reference' => $payment->transaction->reference,
            ]);

            DB::commit();

            return response()->json([
                'redirect_url' => $payment->authorization_url,
                'reference' => $payment->transaction->reference,
                'transaction_id' => $transaction->id
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * Callback NotchPay - TRAITEMENT COMPLET
     */
public function handleCallback(Request $request)
{
    $reference = $request->query('reference');
    $frontendUrl = env('FRONTEND_URL', 'http://localhost:4000');
    
    if (!$reference) {
        return redirect($frontendUrl . '/jeton-history?payment=error&message=reference_missing');
    }

    try {
        // Vérifier le paiement avec NotchPay
        $payment = Payment::verify($reference);
        $notchpayStatus = $payment->transaction->status;
        
        // Trouver la transaction
        $transaction = JetonTransaction::where('notchpay_reference', $reference)->first();

        if (!$transaction) {
            return redirect($frontendUrl . '/jeton-history?payment=error&message=transaction_not_found');
        }

        // Mapper le statut NotchPay vers notre statut local
        $localStatus = $this->mapNotchPayStatus($notchpayStatus);

        DB::beginTransaction();

        // Sauvegarder l'ancien statut pour la logique
        $oldStatus = $transaction->statut;

        // Mettre à jour le statut de la transaction
        $transaction->update([
            'statut' => $localStatus,
            'notchpay_metadata' => array_merge(
                $transaction->notchpay_metadata ?? [],
                [
                    'callback_received_at' => now()->toISOString(),
                    'notchpay_status' => $notchpayStatus,
                    'payment_response' => $payment->transaction
                ]
            )
        ]);

        // Traiter seulement les paiements complétés qui étaient en attente
        if ($localStatus === 'complete' && $oldStatus === 'pending') {
            $this->processSuccessfulPayment($transaction);
        }

        DB::commit();

        Log::info('Callback NotchPay traité', [
            'transaction_id' => $transaction->id,
            'reference' => $reference,
            'ancien_statut' => $oldStatus,
            'nouveau_statut' => $localStatus,
            'notchpay_status' => $notchpayStatus
        ]);

        // Rediriger vers le frontend
        return $this->redirectToFrontend($localStatus, $transaction, $reference);

    } catch (\Exception $e) {
        DB::rollBack();
        
        Log::error('Erreur dans le callback NotchPay:', [
            'reference' => $reference,
            'error' => $e->getMessage()
        ]);

        return redirect($frontendUrl . '/jeton-history?payment=error&message=processing_error');
    }
}

/**
 * Mapper les statuts NotchPay vers nos statuts locaux
 */
private function mapNotchPayStatus($notchpayStatus)
{
    $status = strtolower($notchpayStatus);
    
    return match($status) {
        'complete', 'success', 'completed' => 'complete',
        'failed', 'failure' => 'failed', 
        'canceled', 'cancelled' => 'canceled',
        'expired' => 'expired',
        default => 'pending' // 'pending', 'processing', etc.
    };
}

/**
 * Traiter un paiement réussi
 */
private function processSuccessfulPayment(JetonTransaction $transaction)
{
    // 1. Créditer les jetons à l'acheteur
    $transaction->acheteur->increment('jetons', $transaction->nombre_jetons);

    // 2. Mettre à jour l'offre si marketplace
    if ($transaction->type === 'marketplace' && $transaction->offer_id) {
        $transaction->offer->update(['statut' => 'vendu']);
        
        // 3. LOG pour transfert manuel
        Log::info('💸 TRANSFERT MANUEL REQUIS VERS VENDEUR', [
            'transaction_id' => $transaction->id,
            'vendeur_id' => $transaction->vendeur_id,
            'vendeur_email' => $transaction->vendeur->email,
            'montant_net' => $transaction->montant_net_vendeur,
            'jetons_vendus' => $transaction->nombre_jetons
        ]);
    }

    // 4. Mettre à jour la date de transaction
    $transaction->update([
        'date_transaction' => now(),
    ]);

    Log::info('Jetons crédités avec succès', [
        'transaction_id' => $transaction->id,
        'acheteur_id' => $transaction->acheteur_id,
        'jetons_credites' => $transaction->nombre_jetons
    ]);
}

/**
 * Rediriger vers le frontend avec les paramètres appropriés
 */
private function redirectToFrontend($status, JetonTransaction $transaction, $reference)
{
    $frontendUrl = env('FRONTEND_URL', 'http://localhost:4000');
    $baseUrl = $frontendUrl . '/jeton-history';
    
    $params = [
        'payment' => $status,
        'reference' => $reference,
        'transaction_id' => $transaction->id
    ];

    // Ajouter le nombre de jetons pour les paiements complétés
    if ($status === 'complete') {
        $params['jetons'] = $transaction->nombre_jetons;
    }

    $queryString = http_build_query($params);
    return redirect($baseUrl . '?' . $queryString);
}

    /**
     * Vérifier statut transaction (pour polling frontend)
     */
 /**
 * Vérifier le statut d'une transaction avec NotchPay
 */
public function checkTransactionStatus($transaction_id)
{
    try {
        $transaction = JetonTransaction::with(['acheteur', 'vendeur', 'offer'])
            ->findOrFail($transaction_id);

        // Vérifier si la transaction a une référence NotchPay
        if (!$transaction->notchpay_reference) {
            return response()->json([
                'error' => 'Aucune référence NotchPay trouvée pour cette transaction',
                'transaction' => $transaction,
                'status' => $transaction->statut
            ], 400);
        }

        // Vérifier le statut avec NotchPay
        $payment = Payment::verify($transaction->notchpay_reference);

        // Mettre à jour le statut local selon la réponse NotchPay
        $notchpayStatus = $payment->transaction->status;
        $newStatus = $this->mapNotchPayStatus($notchpayStatus);

        // Si le statut a changé, mettre à jour la transaction
        if ($transaction->statut !== $newStatus) {
            $transaction->update([
                'statut' => $newStatus,
                'notchpay_metadata' => array_merge(
                    $transaction->notchpay_metadata ?? [],
                    [
                        'last_status_check' => now()->toISOString(),
                        'notchpay_status' => $notchpayStatus,
                        'notchpay_response' => $payment->transaction
                    ]
                )
            ]);

            // Si le paiement est maintenant complete, traiter la transaction
            if ($newStatus === 'complete' && $transaction->statut !== 'complete') {
                $this->processSuccessfulPayment($transaction);
            }

            // Recharger la transaction avec les nouvelles données
            $transaction->refresh();
        }

        return response()->json([
            'transaction' => $transaction,
            'status' => $transaction->statut,
            'notchpay_status' => $notchpayStatus,
            'last_checked' => now()->toISOString(),
            'message' => 'Statut vérifié avec succès'
        ], 200);

    } catch (\NotchPay\Exceptions\ApiException $e) {
        // Erreur spécifique à l'API NotchPay
        Log::error('Erreur API NotchPay lors de la vérification:', [
            'transaction_id' => $transaction_id,
            'reference' => $transaction->notchpay_reference ?? 'N/A',
            'error' => $e->getMessage(),
            'code' => $e->getCode()
        ]);

        return response()->json([
            'error' => 'Erreur lors de la vérification avec NotchPay: ' . $e->getMessage(),
            'transaction' => $transaction ?? null,
            'status' => $transaction->statut ?? 'inconnu'
        ], 400);

    } catch (\Exception $e) {
        // Erreur générale
        Log::error('Erreur générale vérification statut:', [
            'transaction_id' => $transaction_id,
            'error' => $e->getMessage()
        ]);

        return response()->json([
            'error' => 'Erreur lors de la vérification: ' . $e->getMessage(),
            'transaction' => $transaction ?? null,
            'status' => $transaction->statut ?? 'inconnu'
        ], 400);
    }
}

    /**
     * Historique des transactions de l'utilisateur
     */
    public function userTransactions(Request $request)
    {
        $user = Auth::user();
        $type = $request->get('type', 'all'); // all, marketplace, platform

        $query = JetonTransaction::where('acheteur_id', $user->id)
            ->orWhere('vendeur_id', $user->id)
            ->with(['acheteur', 'vendeur', 'offer'])
            ->orderBy('created_at', 'desc');

        if ($type !== 'all') {
            $query->where('type', $type);
        }

        $transactions = $query->paginate(20);
            return response()->json([
            'data' => $transactions->items(),
            'current_page' => $transactions->currentPage(),
            'total' => $transactions->total(),
        ]);
    }
}