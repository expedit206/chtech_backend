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
        // Check if $id contains a dashed slug and UUID format (e.g., my-product-123e4567-e89b-12d3-a456-426614174000)
        $numericId = $id;
        if (preg_match('/([a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12})$/i', $id, $matches)) {
            $numericId = $matches[1];
        }

        // Récupérer l'utilisateur via le guard sanctum sans bloquer si absent
        $user = $request->user('sanctum');


            $produit = Produit::with([
                'user:id,nom,email,telephone,photo,created_at',
                'category:id,nom',
                'reviews.user:id,nom,photo',
                'counts',
                'commercant', // Eager load partner info
                'boosts' => function($q) {
                    $q->where('statut', 'actif')->where('end_date', '>', now());
                }
            ])
            ->where('id', $numericId)
            ->orWhere('id', $id)
            ->orWhere('slug', $id)
            ->firstOrFail();

            // Enregistrement de la vue au moment où le détail est ouvert
            $userId = $user ? $user->id : null;
            
            $counts = $produit->counts()->first();
            if ($counts) {
                $counts->increment('clics_count');
            } else {
                $produit->counts()->create(['clics_count' => 1]);
            }

            if ($userId) {
                // Utilisateur connecté : on s'assure qu'on ne compte pas sa vue en double (optionnel ou selon la logique)
                $existingView = \App\Models\ProductView::where('produit_id', $produit->id)
                    ->where('user_id', $userId)
                    ->first();

                if (!$existingView) {
                    \App\Models\ProductView::create([
                        'produit_id' => $produit->id,
                        'user_id' => $userId,
                    ]);
                }
            }

            // Les produits similaires et de la boutique seront chargés de façon asynchrone (Lazy Loading)
            // Vérifier si favori (seulement si utilisateur connecté)
            $isFavorited = false;
            $userId = $user ? $user->id : null;
            if ($user) {
                $isFavorited = \App\Models\ProduitInteraction::where('produit_id', $produit->id)
                    ->where('user_id', $user->id)
                    ->where('type', 'favori')
                    ->exists();
            }

            // Calculer les comptes d'interactions pour éviter une requête HTTP front-end supplémentaire
            $interactionCounts = [
                'clics_count' => $produit->counts ? $produit->counts->clics_count : 0,
                'favorites_count' => \App\Models\ProduitInteraction::where('produit_id', $produit->id)->where('type', 'favori')->count(),
                'partages_count' => \App\Models\ProduitInteraction::where('produit_id', $produit->id)->where('type', 'partage')->count(),
                'contacts_count' => \App\Models\ProduitInteraction::where('produit_id', $produit->id)->where('type', 'contact')->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'produit' => $produit,
                    'isFavorited' => $isFavorited,
                    'counts' => $interactionCounts,
                    'statistics' => [
                        'total_reviews' => $produit->nombre_avis
                    ]
                ]
            ]);
    }

    /**
     * Récupérer les produits similaires (Lazy loaded)
     */
    public function getSimilarProducts($id)
    {
        $produit = Produit::findOrFail($id);
        
        $similarProduits = Produit::with(['user', 'category'])
            ->where('category_id', $produit->category_id)
            ->where('id', '!=', $produit->id)
            ->where('est_actif', true)
            ->latest()
            ->limit(6)
            ->get();

        return response()->json(['success' => true, 'data' => $similarProduits]);
    }

    /**
     * Récupérer les produits du même vendeur (Lazy loaded)
     */
    public function getShopProducts($id)
    {
        $produit = Produit::findOrFail($id);
        
        $shopProduits = Produit::with(['user', 'category'])
            ->where('user_id', $produit->user_id)
            ->where('id', '!=', $produit->id)
            ->where('est_actif', true)
            ->latest()
            ->limit(6)
            ->get();

        return response()->json(['success' => true, 'data' => $shopProduits]);
    }



}