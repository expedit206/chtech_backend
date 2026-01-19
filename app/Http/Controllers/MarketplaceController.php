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

    // PERSONNALISATION SIMPLE SI PAS DE FILTRES EXPLICITES
    $hasExplicitFilters = $request->has('categoryId') || $request->has('search') || $request->has('ville');
    

    
    
    if (!$hasExplicitFilters && $user) {
        
        // CONSTRUCTION DIRECTE DU SCORE
        $scoreParts = [];
        
        // 1. VILLE DE L'UTILISATEUR (60 points)
        if ($user->ville) {
            $scoreParts[] = "CASE WHEN ville LIKE '%{$user->ville}%' THEN 60 ELSE 0 END";
        }
        
        // 2. CATÉGORIES FAVORIES via table d'interactions (40 points)
        // Récupérer les catégories des produits que l'utilisateur a favorisé
        $favoriteCategories = \App\Models\ProduitInteraction::where('interaction_produits.user_id', $user->id)
            ->where('type', 'favori')
            ->whereHas('produit')
            ->with('produit.category')
            ->get()
            ->pluck('produit.category_id')
            ->unique()
            ->filter()
            ->toArray();
        
        // Alternative plus optimisée (requête directe)
        if (empty($favoriteCategories)) {
            $favoriteCategories = \App\Models\ProduitInteraction::where('interaction_produits.user_id', $user->id)
                ->where('type', 'favori')
                ->join('produits', 'interaction_produits.produit_id', '=', 'produits.id')
                ->pluck('produits.category_id')
                ->unique()
                ->filter()
                ->toArray();
        }
        
        if (!empty($favoriteCategories)) {
            $categoriesList = "'" . implode("','", $favoriteCategories) . "'";
            $scoreParts[] = "CASE WHEN category_id IN ({$categoriesList}) THEN 40 ELSE 0 END";
        }
        
        // 3. PRODUITS VUS RÉCEMMENT (20 points) - Basé sur les interactions de type 'vue'
        $recentlyViewedCategories = \App\Models\ProduitInteraction::where('interaction_produits.user_id', $user->id)
            ->where('type', 'clic')
            ->where('interaction_produits.created_at', '>', now()->subDays(7))
            ->join('produits', 'interaction_produits.produit_id', '=', 'produits.id')
            ->pluck('produits.category_id')
            ->unique()
            ->filter()
            ->toArray();
        
        if (!empty($recentlyViewedCategories)) {
            $recentCategoriesList = "'" . implode("','", $recentlyViewedCategories) . "'";
            $scoreParts[] = "CASE WHEN category_id IN ({$recentCategoriesList}) THEN 20 ELSE 0 END";
        }
        
        // 4. POPULARITÉ (Basée sur le nombre total d'interactions)
        $scoreParts[] = "(
            SELECT COUNT(*) FROM interaction_produits 
            WHERE interaction_produits.produit_id = produits.id 
            AND type IN ('favori', 'contact', 'partage')
        ) * 5";
        
        // Ajouter le score au SELECT et trier
        if (!empty($scoreParts)) {
            $selectScore = implode(' + ', $scoreParts);
            $query->selectRaw('produits.*, ' . $selectScore . ' as personal_score');
            
            $query->orderBy('personal_score', 'desc');
        }
        
    } else {
        // COMPORTEMENT NORMAL AVEC FILTRES
        if ($request->has('categoryId') && $request->categoryId !== 'all') {
            $query->where('category_id',  $request->categoryId)
                  ->orWhere('description', 'like', "%{$request->categoryId}%")
                  ->orWhere('nom', 'like', "%{$request->categoryId}%")
            ;
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

    // Tri secondaire par date (si pas de score personnel)
    if (!$hasExplicitFilters && $user && empty($scoreParts)) {
        $query->orderBy('created_at', 'desc');
    }



    // Ajouter les counts d'interactions
    $query->withCount([
        'interactions as favoris_count' => function($q) {
            $q->where('type', 'favori');
        },
        'interactions as vues_count' => function($q) {
            $q->where('type', 'vue');
        },
        'interactions as contacts_count' => function($q) {
            $q->where('type', 'contact');
        }
    ]);

    // Pour l'utilisateur connecté, vérifier si les produits sont dans ses favoris
    if ($user) {
        $produitIds = $query->pluck('id')->toArray();
        
        // Récupérer les IDs des produits favorisés par l'utilisateur
        $userFavorites = \App\Models\ProduitInteraction::where('user_id', $user->id)
            ->where('type', 'favori')
            ->whereIn('produit_id', $produitIds)
            ->pluck('produit_id')
            ->toArray();
        
        // Après avoir récupéré les produits, ajouter l'attribut is_favorited
        $perPage = $request->get('per_page', 8);
        $produits = $query->paginate($perPage);
        
        // Ajouter l'attribut is_favorited à chaque produit
        $produits->getCollection()->transform(function ($produit) use ($userFavorites) {
            $produit->is_favorited = in_array($produit->id, $userFavorites);
            return $produit;
        });
    } else {
        $perPage = $request->get('per_page', 8);
        $produits = $query->paginate($perPage);
    }

    return response()->json([
        'success' => true,
        'produits' => $produits,
        'meta' => [
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
                    ->where(function($q) use ($search) {
                        $q->where('nom', 'like', "%{$search}%")
                          ->orWhere('description', 'like', "%{$search}%")
                          ->orWhereHas('category', function($q) use ($search) {
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
                    ->map(function($item) {
                        $item->result_type = 'product';
                        return $item;
                    });

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
                    // Tri par pertinence : titre d'abord
                    ->orderByRaw("CASE 
                        WHEN titre LIKE ? THEN 1 
                        WHEN titre LIKE ? THEN 2 
                        ELSE 3 END", ["{$search}", "%{$search}%"])
                    ->limit(15)
                    ->get()
                    ->map(function($item) {
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