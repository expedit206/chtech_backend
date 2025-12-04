<?php
// app/Http/Controllers/MarketplaceController.php

namespace App\Http\Controllers;

use App\Models\Produit;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MarketplaceController extends Controller
{
    /**
     * Récupérer les produits pour la marketplace
     */
public function getProduits(Request $request): JsonResponse
{
    try {
        $user = auth()->user();
        $query = Produit::with(['category', 'user'])
            ->where('est_actif', true)
            ->where('quantite', '>', 0);

        // PERSONNALISATION SIMPLE SI PAS DE FILTRES EXPLICITES
        $hasExplicitFilters = $request->has('categoryId') || $request->has('search') || $request->has('ville');
        
        if (!$hasExplicitFilters && $user) {
            
            // CONSTRUCTION DIRECTE DU SCORE
            $scoreParts = [];
            
            // 1. VILLE DE L'UTILISATEUR (60 points)
            if ($user->ville) {
                $scoreParts[] = "CASE WHEN ville LIKE '%{$user->ville}%' THEN 60 ELSE 0 END";
            }
            
            // 2. CATÉGORIES FAVORIES (40 points)
            $favoriteCategories = $user->favoris()
                ->whereHas('produit')
                ->with('produit.category')
                ->get()
                ->pluck('produit.category_id')
                ->unique()
                ->filter()
                ->toArray();
            
            if (!empty($favoriteCategories)) {
                $categoriesList = "'" . implode("','", $favoriteCategories) . "'";
                $scoreParts[] = "CASE WHEN category_id IN ({$categoriesList}) THEN 40 ELSE 0 END";
            }
            
            // Ajouter le score au SELECT et trier
            if (!empty($scoreParts)) {
                $selectScore = implode(' + ', $scoreParts);
                $query->selectRaw('produits.*, ' . $selectScore . ' as personal_score');
                
                $query->orderBy('personal_score', 'desc');
            }
            
        } else {
            // COMPORTEMENT NORMAL AVEC FILTRES
            if ($request->has('categoryId') && $request->categoryId !== 'all') {
                $query->where('category_id', $request->categoryId);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('nom', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhereHas('category', function($q) use ($search) {
                          $q->where('nom', 'like', "%{$search}%");
                      });
                });
            }

            if ($request->has('ville')) {
                $query->where('ville', 'like', "%{$request->ville}%");
            }

        
        }

        // Tri secondaire par date
                // $query->orderBy('personal_score', 'desc');

        $perPage = $request->get('per_page', 8);
        $produits = $query->paginate($perPage);

        // return response()->json([
        //     'success' => true,
        //     'message' => $produits->items(),
        //     'meta' => [
        //         'current_page' => $produits->currentPage(),
        //         'last_page' => $produits->lastPage(),
        //         'per_page' => $produits->perPage(),
        //         'total' => $produits->total(),
        //     ]
        // ],500);

        return response()->json([
            'success' => true,
            'produits' => $produits,
            'meta' => [
                        'current_page' => 1,
                        'current_page' => $produits->currentPage(),
                        'last_page' => $produits->lastPage(),
                        'per_page' => $produits->perPage(),
                        'total' => $produits->total(),
            ]
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Erreur lors du chargement des produits',
            'error' => $e->getMessage()
        ], 500);
    }
}
    /**
     * Récupérer les services pour la marketplace
     */
    public function getServices(Request $request): JsonResponse
    {
        try {
            $query = Service::with(['category', 'user'])
                ->where('disponibilite', 'disponible');

            // Filtrage par catégorie
            if ($request->has('category_id') && $request->category_id !== 'all') {
                $query->where('id_categorie', $request->category_id);
            }

            // Filtrage par recherche
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('titre', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhere('competences', 'like', "%{$search}%")
                      ->orWhereHas('category', function($q) use ($search) {
                          $q->where('nom', 'like', "%{$search}%");
                      });
                });
            }

            // Filtrage par ville
            if ($request->has('ville')) {
                $query->where('ville', 'like', "%{$request->ville}%");
            }

            // Tri
            switch ($request->get('sort', 'newest')) {
                case 'price_asc':
                    $query->orderBy('prix', 'asc');
                    break;
                case 'price_desc':
                    $query->orderBy('prix', 'desc');
                    break;
                case 'popular':
                    $query->orderBy('note_moyenne', 'desc')
                          ->orderBy('nombre_avis', 'desc');
                    break;
                default:
                    $query->orderBy('created_at', 'desc');
            }

            $services = $query->paginate($request->get('per_page', 24));

            return response()->json([
                'success' => true,
                'data' => $services->items(),
                'meta' => [
                    'current_page' => $services->currentPage(),
                    'last_page' => $services->lastPage(),
                    'per_page' => $services->perPage(),
                    'total' => $services->total(),
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors du chargement des services',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Recherche globale dans produits et services
     */
    public function globalSearch(Request $request): JsonResponse
    {
        try {
            $search = $request->get('q', '');
            $type = $request->get('type', 'all');

            $results = [];

            if ($type === 'all' || $type === 'products') {
                $products = Produit::with(['category', 'user'])
                    ->where('est_actif', true)
                    ->where('quantite', '>', 0)
                    ->where(function($q) use ($search) {
                        $q->where('nom', 'like', "%{$search}%")
                          ->orWhere('description', 'like', "%{$search}%")
                          ->orWhereHas('category', function($q) use ($search) {
                              $q->where('nom', 'like', "%{$search}%");
                          });
                    })
                    ->limit(10)
                    ->get();

                $results['products'] = $products;
            }

            if ($type === 'all' || $type === 'services') {
                $services = Service::with(['category', 'user'])
                    ->where('disponibilite', 'disponible')
                    ->where(function($q) use ($search) {
                        $q->where('titre', 'like', "%{$search}%")
                          ->orWhere('description', 'like', "%{$search}%")
                          ->orWhere('competences', 'like', "%{$search}%")
                          ->orWhereHas('category', function($q) use ($search) {
                              $q->where('nom', 'like', "%{$search}%");
                          });
                    })
                    ->limit(10)
                    ->get();

                $results['services'] = $services;
            }

            return response()->json([
                'success' => true,
                'data' => $results
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