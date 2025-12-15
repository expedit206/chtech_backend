<?php

// app/Services/InteractionService.php
namespace App\Services;

use App\Models\ProduitInteraction;
use App\Models\ServiceInteraction ;
use App\Models\Produit;
use App\Models\Service;
use Illuminate\Support\Facades\Auth;

class InteractionService
{
    /**
     * Enregistre une interaction utilisateur
     */
    public function recordInteraction($contentId, $contentType, $type, $metadata = null)
    {
        $user = Auth::user();
        if (!$user) return null;

        $model = $this->getModel($contentType);
        $foreignKey = $this->getForeignKey($contentType);

        // Mise à jour ou création
        return $model::updateOrCreate(
            [
                'user_id' => $user->id,
                $foreignKey => $contentId,
                'type' => $type
            ],
            [
                'metadata' => $metadata,
                'updated_at' => now()
            ]
        );
    }

    /**
     * Toggle une interaction (favori/like)
     */
    public function toggleInteraction($contentId, $contentType, $type)
    {
        $user = Auth::user();
        if (!$user) return ['status' => 'error', 'message' => 'Non authentifié'];

        $model = $this->getModel($contentType);
        $foreignKey = $this->getForeignKey($contentType);

        // Vérifier si l'interaction existe
        $existing = $model::where([
            'user_id' => $user->id,
            $foreignKey => $contentId,
            'type' => $type
        ])->first();

        if ($existing) {
            $existing->delete();
            return [
                'status' => 'removed',
                'action' => 'retiré',
                'count' => $this->getInteractionCount($contentId, $contentType, $type)
            ];
        }

        // Créer la nouvelle interaction
        $model::create([
            'user_id' => $user->id,
            $foreignKey => $contentId,
            'type' => $type,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return [
            'status' => 'added',
            'action' => 'ajouté',
            'count' => $this->getInteractionCount($contentId, $contentType, $type)
        ];
    }

    /**
     * Récupère les interactions d'un utilisateur
     */
    public function getUserInteractions($contentType, $userId = null, $types = null, $limit = 50)
    {
        $userId = $userId ?? Auth::id();
        if (!$userId) return collect();

        $model = $this->getModel($contentType);
        $query = $model::where('user_id', $userId);

        if ($types) {
            if (is_array($types)) {
                $query->whereIn('type', $types);
            } else {
                $query->where('type', $types);
            }
        }

        return $query->with($this->getContentRelation($contentType))
                     ->orderBy('created_at', 'desc')
                     ->limit($limit)
                     ->get();
    }

    /**
     * Récupère les favoris d'un utilisateur
     */
    public function getUserFavorites($contentType, $userId = null)
    {
        return $this->getUserInteractions($contentType, $userId, 'favori');
    }

    /**
     * Vérifie si un contenu est favorisé par l'utilisateur
     */
    public function isFavorited($contentId, $contentType, $userId = null)
    {
        $userId = $userId ?? Auth::id();
        if (!$userId) return false;

        $model = $this->getModel($contentType);
        $foreignKey = $this->getForeignKey($contentType);

        return $model::where([
            'user_id' => $userId,
            $foreignKey => $contentId,
            'type' => 'favori'
        ])->exists();
    }

    /**
     * Récupère le nombre d'interactions pour un contenu
     */
    public function getInteractionCount($contentId, $contentType, $type = null)
    {
        $model = $this->getModel($contentType);
        $foreignKey = $this->getForeignKey($contentType);

        $query = $model::where($foreignKey, $contentId);

        if ($type) {
            $query->where('type', $type);
        }

        return $query->count();
    }

    /**
     * Récupère les catégories préférées d'un utilisateur
     */
    public function getPreferredCategories($userId = null)
    {
        $userId = $userId ?? Auth::id();
        if (!$userId) return collect();

        // Interactions produits
        $productInteractions = InteractionProduit::where('user_id', $userId)
            ->whereIn('type', ['favori', 'contact'])
            ->with('produit.category')
            ->get();

        // Interactions services
        $serviceInteractions = InteractionServiceModel::where('user_id', $userId)
            ->whereIn('type', ['favori', 'contact'])
            ->with('service.category')
            ->get();

        // Collecte des catégories avec poids
        $categories = collect();

        foreach ($productInteractions as $interaction) {
            if ($interaction->produit && $interaction->produit->category) {
                $categories->push([
                    'id' => $interaction->produit->category->id,
                    'name' => $interaction->produit->category->nom,
                    'type' => 'produit',
                    'weight' => $this->getTypeWeight($interaction->type)
                ]);
            }
        }

        foreach ($serviceInteractions as $interaction) {
            if ($interaction->service && $interaction->service->category) {
                $categories->push([
                    'id' => $interaction->service->category->id,
                    'name' => $interaction->service->category->nom,
                    'type' => 'service',
                    'weight' => $this->getTypeWeight($interaction->type)
                ]);
            }
        }

        // Regroupement et calcul du score
        return $categories->groupBy('id')->map(function ($group) {
            return [
                'id' => $group->first()['id'],
                'name' => $group->first()['name'],
                'type' => $group->first()['type'],
                'total_weight' => $group->sum('weight'),
                'interaction_count' => $group->count()
            ];
        })->sortByDesc('total_weight')->values();
    }

    /**
     * Récupère les suggestions basées sur les interactions
     */
    public function getSuggestions($userId = null, $limit = 10)
    {
        $userId = $userId ?? Auth::id();
        if (!$userId) return collect();

        $preferredCategories = $this->getPreferredCategories($userId);

        if ($preferredCategories->isEmpty()) {
            // Retourner des suggestions populaires si pas de préférences
            return $this->getPopularContent($limit);
        }

        // Récupérer les produits/services des catégories préférées
        $suggestions = collect();

        foreach ($preferredCategories->take(3) as $category) {
            if ($category['type'] === 'produit') {
                $items = Produit::where('category_id', $category['id'])
                    ->where('user_id', '!=', $userId) // Pas ses propres produits
                    ->inRandomOrder()
                    ->limit(ceil($limit / 3))
                    ->get();
                
                $items->each(function ($item) use (&$suggestions, $category) {
                    $suggestions->push([
                        'type' => 'produit',
                        'data' => $item,
                        'category' => $category,
                        'relevance_score' => $category['total_weight']
                    ]);
                });
            } else {
                $items = Service::where('category_id', $category['id'])
                    ->where('user_id', '!=', $userId)
                    ->inRandomOrder()
                    ->limit(ceil($limit / 3))
                    ->get();
                
                $items->each(function ($item) use (&$suggestions, $category) {
                    $suggestions->push([
                        'type' => 'service',
                        'data' => $item,
                        'category' => $category,
                        'relevance_score' => $category['total_weight']
                    ]);
                });
            }
        }

        return $suggestions->shuffle()->take($limit);
    }

    /**
     * Méthodes helper privées
     */
    private function getModel($contentType)
    {
        return $contentType === 'produit' 
            ? ProduitInteraction::class 
            : ServiceInteraction::class;
    }

    private function getForeignKey($contentType)
    {
        return $contentType === 'produit' ? 'produit_id' : 'service_id';
    }

    private function getContentRelation($contentType)
    {
        return $contentType === 'produit' ? 'produit' : 'service';
    }

    private function getTypeWeight($type)
    {
        $weights = [
            'favori' => 3,
            'contact' => 2,
            'vue' => 1,
            'partage' => 2,
            'commentaire' => 2
        ];

        return $weights[$type] ?? 1;
    }

    private function getPopularContent($limit = 10)
    {
        // Récupérer les produits/services les plus interactifs
        $popularProducts = Produit::withCount(['interactions as favorite_count' => function($query) {
            $query->where('type', 'favori');
        }])
        ->orderBy('favorite_count', 'desc')
        ->limit($limit)
        ->get();

        return $popularProducts->map(function ($product) {
            return [
                'type' => 'produit',
                'data' => $product,
                'relevance_score' => $product->favorite_count
            ];
        });
    }
}