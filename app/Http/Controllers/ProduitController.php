<?php
// app/Http/Controllers/ProduitController.php
namespace App\Http\Controllers;

use App\Models\user;
use App\Models\Boost;
use Ramsey\Uuid\Uuid;
use App\Models\Produit;
use App\Models\ProductView;
use Illuminate\Http\Request;
use App\Jobs\RecordProductView;
use App\Models\ProductFavorite;
use App\Models\JetonTransaction;
use App\Models\JetonsTransaction;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Redis;

class ProduitController extends Controller
{

   




    /**
     * Affiche les détails d'un produit spécifique (gère les accès publics et authentifiés)
     */
    public function show($id, Request $request): JsonResponse
    {
        try {
            // Récupérer l'utilisateur via le guard sanctum sans bloquer si absent
            $user = $request->user('sanctum');

            $produit = Produit::with([
                'user:id,nom,email,telephone,photo,created_at',
                'category:id,nom',
                'reviews.user:id,nom,photo',
                'counts',
                'boosts' => function($q) {
                    $q->where('statut', 'actif')->where('end_date', '>', now());
                }
            ])
            ->findOrFail($id);

            // Produits similaires (même catégorie, exclure le produit actuel)
            $similarProduits = Produit::with(['user', 'category'])
                ->where('category_id', $produit->category_id)
                ->where('id', '!=', $produit->id)
                ->where('est_actif', true)
                ->limit(6)
                ->get();

            // Vérifier si favori (seulement si utilisateur connecté)
            $isFavorited = false;
            if ($user) {
                $isFavorited = \App\Models\ProduitInteraction::where('produit_id', $id)
                    ->where('user_id', $user->id)
                    ->where('type', 'favori')
                    ->exists();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'produit' => $produit,
                    'isFavorited' => $isFavorited,
                    'similar_produits' => $similarProduits,
                    'statistics' => [
                        'total_reviews' => $produit->nombre_avis
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => "Produit non trouvé ou erreur : " . $e->getMessage()
            ], 404);
        }
    }

    /**
     * Enregistre une vue pour un produit (visiteur public)
     */
   public function publicRecordView(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:produits,id',
        ]);

        $produitId = $validated['product_id'];
        $produit = Produit::findOrFail($produitId);

        // Incrémenter views_count pour les non-connectés
        $produit->counts()->updateOrCreate(
            ['produit_id' => $produitId],
            ['views_count' => \DB::raw('views_count + 1')]
        );

        return response()->json(['message' => 'Vue enregistrée']);
    }

    /**
     * Enregistre ou met à jour une vue pour un produit (utilisateur connecté)
     */
    public function recordView(Request $request)
    {

        $validated = $request->validate([
            'product_id' => 'required|exists:produits,id',
            'user_id' => 'nullable|exists:users,id',
        ]);

        // return response()->json(['message' => $request->all()]);


        $produitId = $validated['product_id'];
        $userId = $validated['user_id'] ?? null;
        $produit = Produit::findOrFail($produitId);

        if (!$request->user()) {

            $produit->counts()->updateOrCreate(
                ['produit_id' => $produitId],
                ['views_count' => \DB::raw('views_count + 1')]
            );

            return response()->json([
                'message' => 'Vue enregistrée1',
                'user' => $request->all()
            ]);
        }
        // Vérifier si l'utilisateur a déjà vu ce produit
        $existingView = ProductView::where('produit_id', $produitId)
            ->where('user_id', $userId)
            ->first();

        if (!$existingView) {
            // Nouvelle vue, incrémenter views_count et enregistrer dans product_views
            $produit->counts()->updateOrCreate(
                ['produit_id' => $produitId],
                ['views_count' => \DB::raw('views_count + 1')]
            );

            ProductView::create([
                'produit_id' => $produitId,
                'user_id' => $userId,
            ]);

            return response()->json(['message' => 'Vue enregistrée']);
        } else {
            // Vue déjà enregistrée
            return response()->json(['message' => 'Vue déjà enregistrée'], 200);
        }
    }


    
}