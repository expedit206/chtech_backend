<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PlatformReview;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AdminPlatformReviewController extends Controller
{
    /**
     * Récupère tous les avis plateforme avec pagination
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search');
            
            $query = PlatformReview::with(['user:id,nom,photo,email'])
                ->orderBy('created_at', 'desc');
            
            if ($search) {
                $query->whereHas('user', function ($q) use ($search) {
                    $q->where('nom', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
                })
                ->orWhere('message', 'like', "%{$search}%");
            }
            
            $reviews = $query->paginate($perPage);
            
            // Statistiques
            $stats = [
                'total' => PlatformReview::count(),
                'average_rating' => round(PlatformReview::avg('rating'), 1),
                'rating_breakdown' => [
                    5 => PlatformReview::where('rating', 5)->count(),
                    4 => PlatformReview::where('rating', 4)->count(),
                    3 => PlatformReview::where('rating', 3)->count(),
                    2 => PlatformReview::where('rating', 2)->count(),
                    1 => PlatformReview::where('rating', 1)->count(),
                ]
            ];
            
            return response()->json([
                'success' => true,
                'data' => $reviews->items(),
                'stats' => $stats,
                'pagination' => [
                    'current_page' => $reviews->currentPage(),
                    'per_page' => $reviews->perPage(),
                    'total' => $reviews->total(),
                    'last_page' => $reviews->lastPage(),
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des avis',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
    
    /**
     * Récupère un avis spécifique
     */
    public function show($id): JsonResponse
    {
        try {
            $review = PlatformReview::with(['user:id,nom,photo,email'])->findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => $review
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Avis non trouvé',
            ], 404);
        }
    }
    
    /**
     * Supprime un avis
     */
    public function destroy($id): JsonResponse
    {
        try {
            $review = PlatformReview::findOrFail($id);
            $review->delete();
            
            return response()->json([
                'success' => true,
                'message' => 'L\'avis a été supprimé avec succès'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de l\'avis',
            ], 500);
        }
    }
    
    /**
     * Obtient les statistiques des avis
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = [
                'total' => PlatformReview::count(),
                'average_rating' => round(PlatformReview::avg('rating'), 1),
                'total_with_message' => PlatformReview::whereNotNull('message')->count(),
                'rating_distribution' => [
                    5 => PlatformReview::where('rating', 5)->count(),
                    4 => PlatformReview::where('rating', 4)->count(),
                    3 => PlatformReview::where('rating', 3)->count(),
                    2 => PlatformReview::where('rating', 2)->count(),
                    1 => PlatformReview::where('rating', 1)->count(),
                ],
                'recent' => PlatformReview::with(['user:id,nom,photo'])
                    ->latest('created_at')
                    ->limit(5)
                    ->get()
            ];
            
            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des statistiques',
            ], 500);
        }
    }
}
