<?php

namespace App\Http\Controllers;

use App\Models\Produit;
use App\Models\Service;
use Illuminate\Http\Request;
use App\Models\ProduitReview;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

class ProduitReviewController extends Controller
{


       /**
     * Soumettre un nouvel avis pour un produit
     */
    /**
     * Enregistre un nouvel avis client pour un produit et recalcule sa note moyenne
     */
    public function storeProduitReview(Request $request, $productId): JsonResponse
    {
        try {
            $request->validate([
                'rating' => 'required|integer|min:1|max:5',
                'comment' => 'required|string|min:2|max:1000',
            ]);

            $product = Produit::findOrFail($productId);
            $user = $request->user();

            // Vérifier si l'utilisateur a déjà laissé un avis
            $existingReview = ProduitReview::where('user_id', $user->id)
                ->where('produit_id', $productId)
                ->first();

            if ($existingReview) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous avez déjà laissé un avis pour ce produit'
                ], 422);
            }

            // Créer l'avis
            $review = ProduitReview::create([
                'user_id' => $user->id,
                'produit_id' => $product->id,
                'provider_id' => $product->user_id,
                'rating' => $request->rating,
                'comment' => $request->comment,
            ]);

            // Mettre à jour les statistiques du produit
            $this->recalculateProductStats($product);

            return response()->json([
                'success' => true,
                'message' => 'Votre avis a été publié avec succès',
                'data' => [
                    'review' => $review,
                    'new_average' => $product->fresh()->note_moyenne,
                    'total_reviews' => $product->fresh()->nombre_avis
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Erreur création avis produit', [
                'produit_id' => $productId,
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => config('app.debug') ? $e->getMessage() : 'Erreur interne du serveur',
                'error' => config('app.debug') ? $e->getMessage() : 'Erreur interne du serveur'
            ], 500);
        }
    }

    /**
     * Récupérer les avis d'un produit
     */
    /**
     * Liste les avis paginés pour un produit spécifique
     */
    public function index($productId, Request $request): JsonResponse
    {
        try {
            $product = Produit::findOrFail($productId);

            $reviews = ProduitReview::where('produit_id', $productId)
                ->with(['user:id,nom,photo,created_at'])
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 10));

            $averageRating = $product->note_moyenne;
            $totalReviews = $product->nombre_avis;

            return response()->json([
                'success' => true,
                'data' => [
                    'reviews' => $reviews,
                    'statistics' => [
                        'average_rating' => $averageRating,
                        'total_reviews' => $totalReviews,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des avis',
                'error' => $e->getMessage()

            ], 500);
        }
    }

    /**
     * Recalcule les statistiques d'un produit
     */
    private function recalculateProductStats(Produit $product): void
    {
        $stats = ProduitReview::where('produit_id', $product->id)
            ->selectRaw('COUNT(*) as total_reviews, AVG(rating) as average_rating')
            ->first();

        $product->update([
            'nombre_avis' => $stats->total_reviews ?? 0,
            'note_moyenne' => round($stats->average_rating ?? 0, 2)
        ]);
    }
}
