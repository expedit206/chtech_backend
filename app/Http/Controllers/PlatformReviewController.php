<?php

namespace App\Http\Controllers;

use App\Models\PlatformReview;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PlatformReviewController extends Controller
{
    /**
     * Soumettre un nouvel avis plateforme
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'rating' => 'required|integer|min:1|max:5',
                'message' => 'nullable|string|max:1000',
            ]);

            $user = $request->user();
            // Vérifier si l'utilisateur a déjà laissé un avis cette semaine
            $existingReview = PlatformReview::where('user_id', $user->id)
            ->where('created_at', '>=', now()->subDays(7))
            ->first();
            
            if ($existingReview) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vous pouvez laisser qu\'un avis par semaine'
                ], 422);
                return response()->json([
                                'success' => false,
                                'message' => 'Vous pouvez laisser qu\'un avis par semaine'
                            ], 400);
            }

            // Créer l'avis
            $review = PlatformReview::create([
                'user_id' => $user->id,
                'rating' => $request->rating,
                'message' => $request->message,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Merci pour votre avis! Nous l\'avons bien reçu.',
                'data' => $review
            ], 201);

        } catch (\Exception $e) {
            \Log::error('Erreur création avis plateforme', [
                'user_id' => $request->user()->id ?? null,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => config('app.debug') ? $e->getMessage() : 'Erreur interne du serveur',
            ], 500);
        }
    }

    /**
     * Récupérer les avis plateforme (publics)
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 10);
            
            $reviews = PlatformReview::with(['user:id,nom,photo'])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            $averageRating = round(PlatformReview::avg('rating'), 1);
            $totalReviews = PlatformReview::count();

            return response()->json([
                'success' => true,
                'data' => [
                    'reviews' => $reviews->items(),
                    'statistics' => [
                        'average_rating' => $averageRating,
                        'total_reviews' => $totalReviews,
                    ],
                    'pagination' => [
                        'current_page' => $reviews->currentPage(),
                        'per_page' => $reviews->perPage(),
                        'total' => $reviews->total(),
                        'last_page' => $reviews->lastPage(),
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des avis',
            ], 500);
        }
    }
}
