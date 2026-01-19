<?php
// app/Http/Controllers/ServiceReviewController.php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\ServiceReview;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ServiceReviewController extends Controller
{
    /**
     * Soumettre un nouvel avis
     */
    /**
     * Enregistre un nouvel avis pour un service et met à jour sa note moyenne
     */
    public function storeServiceReview(Request $request, $serviceId): JsonResponse
    {
        try {
            $request->validate([
                'rating' => 'required|integer|min:1|max:5',
                'comment' => 'required|string|min:2|max:1000',
            ]);

            $service = Service::findOrFail($serviceId);
            $user = $request->user();

            // Vérifier si l'utilisateur a déjà laissé un avis
            $existingReview = ServiceReview::where('user_id', $user->id)
                ->where('service_id', $serviceId)
                ->first();

            if ($existingReview) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous avez déjà laissé un avis pour ce service'
                ], 422);
            }

            DB::transaction(function () use ($user, $service, $request) {
                // Créer l'avis
                $review = ServiceReview::create([
                    'user_id' => $user->id,
                    'service_id' => $service->id,
                    'provider_id' => $service->user_id,
                    'rating' => $request->rating,
                    'comment' => $request->comment,
                ]);
                $service->calculateAverageRating();

                // Mettre à jour la note moyenne du service
            });

            return response()->json([
                'success' => true,
                'message' => 'Votre avis a été publié avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mettre à jour un avis
     */
    /**
     * Met à jour un avis de service existant
     */
    public function update(Request $request, $reviewId): JsonResponse
    {
        try {
            $request->validate([
                'rating' => 'sometimes|integer|min:1|max:5',
                'comment' => 'sometimes|string|min:10|max:1000',
                'rating_breakdown' => 'sometimes|array',
            ]);

            $review = ServiceReview::where('user_id', $request->user()->id)
                ->findOrFail($reviewId);

            DB::transaction(function () use ($review, $request) {
                $review->update($request->only(['rating', 'comment', 'rating_breakdown']));
                
                // Recalculer la note moyenne du service
                $review->service->calculateAverageRating();
            });

            return response()->json([
                'success' => true,
                'message' => 'Avis mis à jour avec succès',
                'data' => $review->fresh(['user'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de l\'avis'
            ], 500);
        }
    }

    /**
     * Répondre à un avis (prestataire)
     */
    /**
     * Permet au prestataire de répondre à un avis client
     */
    public function respond(Request $request, $reviewId): JsonResponse
    {
        try {
            $request->validate([
                'response' => 'required|string|min:5|max:500'
            ]);

            $review = ServiceReview::where('provider_id', $request->user()->id)
                ->findOrFail($reviewId);

            $review->update([
                'provider_response' => $request->response,
                'responded_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Réponse publiée avec succès',
                'data' => $review->fresh(['user', 'provider'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la publication de la réponse'
            ], 500);
        }
    }

    /**
     * Récupérer les avis d'un service avec pagination
     */
    /**
     * Liste les avis paginés pour un service spécifique avec statistiques globales
     */
    public function index($serviceId, Request $request): JsonResponse
    {
        try {
            $service = Service::findOrFail($serviceId);

            $reviews = $service->reviews()
                ->with(['user:id,nom,photo,created_at'])
                ->orderBy('created_at', 'desc')
                ->paginate('10');

            $averageRating = $service->note_moyenne;
            $totalReviews = $service->nombre_avis;

            return response()->json([
                'success' => true,
                'data' => [
                    'reviews' => $reviews,
                    'statistics' => [
                        'average_rating' => 5,
                        // 'average_rating' => $averageRating,
                        'total_reviews' => $totalReviews,
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }


}