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

   




    public function show($id): JsonResponse
    {
        try {
            $produit = Produit::with([
                'user:id,nom,email,telephone,photo,created_at',
                'category:id,nom',
                'reviews.user:id,nom,photo'
            ])
            ->findOrFail($id);

            // Incrémenter le compteur de vues
            // $produit->increment('views_count');

            // Produits similaires (même catégorie)
            $similarProduits = Produit::with(['user', 'category'])
                ->where('category_id', $produit->category_id)
                ->where('id', '!=', $produit->id)
                ->limit(6)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'produit' => $produit,
                    'isFavorited' => $produit->isFavorited(),
                    'similar_produits' => $similarProduits,
                    'statistics' => [
                        'average_rating' => $produit->note_moyenne,
                        'total_reviews' => $produit->nombre_avis
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => $e->getMessage()
            ], 404);
        }
    }


public function publicShow($id, Request $request)
    {
        // Récupérer le produit sans dépendre de l'utilisateur
        $produit = Produit::with(['user', 'category'])
            ->with('counts')
            ->findOrFail($id);

        // Ajouter uniquement les propriétés publiques
        $produit->user->rating = $produit->user->average_rating; // Attribut calculé
        $produit['boosted_until'] = $produit->boosts->first()?->end_date;
        $produit->is_favorited_by = false; // Par défaut pour les non-connectés

        return response()->json(['produit' => $produit->load('counts')]);
    }

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