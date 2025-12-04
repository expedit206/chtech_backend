<?php
// app/Http/Controllers/ServiceController.php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

class ServiceController extends Controller
{
    // Liste des services avec filtres
    public function index(Request $request)
    {
        try {
            $query = Service::with(['categorie', 'user'])
                ->disponible();

            // Filtres
            if ($request->has('categorie')) {
                $query->byCategorie($request->categorie);
            }

            if ($request->has('ville')) {
                $query->byVille($request->ville);
            }

            if ($request->has('type_service')) {
                $query->where('type_service', $request->type_service);
            }

            if ($request->has('prix_min')) {
                $query->where('prix', '>=', $request->prix_min);
            }

            if ($request->has('prix_max')) {
                $query->where('prix', '<=', $request->prix_max);
            }

            // Tri
            $sort = $request->get('sort', 'created_at');
            $order = $request->get('order', 'desc');

            $query->orderBy($sort, $order);

            $services = $query->paginate($request->get('per_page', 12));

            return response()->json([
                'success' => true,
                'data' => $services,
                'message' => 'Services récupérés avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' =>  $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Afficher un service spécifique
  
    public function show($id): JsonResponse
    {
        try {
            $service = Service::with([
                'user:id,nom,email,telephone,photo,created_at',
                'category:id,nom',
                'reviews.user:id,nom,photo'
            ])
            ->findOrFail($id);

            // Incrémenter le compteur de vues
            // $service->increment('views_count');

            // Services similaires (même catégorie)
            $similarServices = Service::with(['user', 'category'])
                ->where('category_id', $service->category_id)
                ->where('id', '!=', $service->id)
                ->limit(6)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'service' => $service,
                    'similar_services' => $similarServices,
                    'statistics' => [
                        'average_rating' => $service->note_moyenne,
                        'total_reviews' => $service->nombre_avis
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

     public function getReviews($id): JsonResponse
    {
        try {
            $reviews = Service::findOrFail($id)
                ->reviews()
                ->with('user:id,nom,photo')
                ->orderBy('created_at', 'desc')
                ->paginate(10);

            return response()->json([
                'success' => true,
                'data' => $reviews
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des avis'
            ], 500);
        }
    }



      public function search(Request $request)
    {
        try {
            $query = Service::with(['categorie', 'user'])
                ->where('disponibilite', 'disponible');

            if ($request->has('q')) {
                $searchTerm = $request->q;
                $query->where(function($q) use ($searchTerm) {
                    $q->where('titre', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('description', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('competences', 'LIKE', "%{$searchTerm}%");
                });
            }

            $services = $query->paginate($request->get('per_page', 12));

            return response()->json([
                'success' => true,
                'data' => $services,
                'message' => 'Résultats de recherche récupérés avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la recherche',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}