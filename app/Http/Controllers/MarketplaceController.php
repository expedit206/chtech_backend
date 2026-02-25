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
     * Récupère la liste des produits pour la marketplace avec filtres et scores
     */
    public function getProduits(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $query = Produit::with(['category', 'user'])
                ->where('est_actif', true)
                ->where('quantite', '>', 0);

            // PERSONNALISATION : PLUS DE SCORE LOURD, JUSTE DU RANDOM OU FILTRES
            $hasExplicitFilters = $request->has('categoryId') || $request->has('search') || $request->has('ville');

            if ($hasExplicitFilters) {
                // COMPORTEMENT NORMAL AVEC FILTRES
                if ($request->has('categoryId') && $request->categoryId !== 'all') {
                    $query->where('category_id',  $request->categoryId);
                }

                if ($request->has('search')) {
                    $search = $request->search;
                    $query->where(function ($q) use ($search) {
                        $q->where('nom', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%")
                            ->orWhereHas('category', function ($q) use ($search) {
                                $q->where('nom', 'like', "%{$search}%");
                            });
                    });
                }

                if ($request->has('ville')) {
                    $query->where('ville', 'like', "%{$request->ville}%");
                }

                $query->orderBy('is_promoted', 'desc')->orderBy('created_at', 'desc');
            } else {
                // AFFICHAGE ALÉATOIRE POUR LA PERFORMANCE ET LA DÉCOUVERTE
                $query->inRandomOrder();
            }



            // Ajouter les counts d'interactions
            $query->withCount([
                'interactions as favoris_count' => function ($q) {
                    $q->where('type', 'favori');
                },
                'interactions as vues_count' => function ($q) {
                    $q->where('type', 'vue');
                },
                'interactions as contacts_count' => function ($q) {
                    $q->where('type', 'contact');
                }
            ]);

            // Pagination d'abord (simplePaginate est plus rapide)
            $perPage = $request->get('per_page', 25);
            $produits = $query->simplePaginate($perPage);

            // Pour l'utilisateur connecté, vérifier si les produits sont dans ses favoris
            if ($user && $produits->count() > 0) {
                // On ne récupère les favoris que pour les produits de la page actuelle
                $currentPageIds = $produits->getCollection()->pluck('id')->toArray();

                $userFavorites = \App\Models\ProduitInteraction::where('user_id', $user->id)
                    ->where('type', 'favori')
                    ->whereIn('produit_id', $currentPageIds)
                    ->pluck('produit_id')
                    ->toArray();

                // Ajouter l'attribut is_favorited à chaque produit
                $produits->getCollection()->transform(function ($produit) use ($userFavorites) {
                    $produit->is_favorited = in_array($produit->id, $userFavorites);
                    return $produit;
                });
            }

            return response()->json([
                'success' => true,
                'produits' => $produits->items(),
                'meta' => [
                    'current_page' => $produits->currentPage(),
                    'per_page' => $produits->perPage(),
                    'has_more_pages' => $produits->hasMorePages(),
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
     * Récupère la liste des services disponibles sur la marketplace
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
                $query->where(function ($q) use ($search) {
                    $q->where('titre', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('competences', 'like', "%{$search}%")
                        ->orWhereHas('category', function ($q) use ($search) {
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

            $services = $query->simplePaginate($request->get('per_page', 24));

            return response()->json([
                'success' => true,
                'data' => $services->items(),
                'meta' => [
                    'current_page' => $services->currentPage(),
                    'per_page' => $services->perPage(),
                    'has_more_pages' => $services->hasMorePages(),
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
     * Effectue une recherche globale optimisée dans les produits et services
     */
    public function globalSearch(Request $request): JsonResponse
    {
        try {
            $search = $request->get('q', '');
            $type = $request->get('type', 'all');

            if (empty($search) || strlen($search) < 2) {
                return response()->json([
                    'success' => true,
                    'data' => ['products' => [], 'services' => []]
                ]);
            }

            $results = [];

            if ($type === 'all' || $type === 'products') {
                $products = Produit::with(['category', 'user'])
                    ->where('est_actif', true)
                    ->where('quantite', '>', 0)
                    ->where(function ($q) use ($search) {
                        $q->where('nom', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%")
                            ->orWhereHas('category', function ($q) use ($search) {
                                $q->where('nom', 'like', "%{$search}%");
                            });
                    })
                    // Tri par pertinence : nom d'abord, puis description
                    ->orderByRaw("CASE 
                        WHEN nom LIKE ? THEN 1 
                        WHEN nom LIKE ? THEN 2 
                        ELSE 3 END", ["{$search}", "%{$search}%"])
                    ->limit(15)
                    ->get()
                    ->map(function ($item) {
                        $item->result_type = 'product';
                        return $item;
                    });

                $results['products'] = $products;
            }

            if ($type === 'all' || $type === 'services') {
                $services = Service::with(['category', 'user'])
                    ->where('disponibilite', 'disponible')
                    ->where(function ($q) use ($search) {
                        $q->where('titre', 'like', "%{$search}%")
                            ->orWhere('description', 'like', "%{$search}%")
                            ->orWhere('competences', 'like', "%{$search}%")
                            ->orWhereHas('category', function ($q) use ($search) {
                                $q->where('nom', 'like', "%{$search}%");
                            });
                    })
                    // Tri par pertinence : titre d'abord
                    ->orderByRaw("CASE 
                        WHEN titre LIKE ? THEN 1 
                        WHEN titre LIKE ? THEN 2 
                        ELSE 3 END", ["{$search}", "%{$search}%"])
                    ->limit(15)
                    ->get()
                    ->map(function ($item) {
                        $item->result_type = 'service';
                        return $item;
                    });

                $results['services'] = $services;
            }

            return response()->json([
                'success' => true,
                'data' => $results,
                'search_query' => $search
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
