<?php

namespace App\Http\Controllers;

use App\Models\Produit;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

class FavoriteController extends Controller
{
    /**
     * Liste tous les favoris de l'utilisateur
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = $request->user();

            // Récupérer les produits favoris avec leurs détails
            $produits = $user->produitFavorites()
                ->where('type', 'favori')
                ->with(['produit.category', 'produit.user'])
                ->latest()
                ->get()
                ->map(function ($interaction) {
                    return $interaction->produit;
                })
                ->filter(); // Enlever les nulls si des produits ont été supprimés

            // Récupérer les services favoris avec leurs détails
            $services = $user->serviceFavorites()
                ->where('type', 'favori')
                ->with(['service.category', 'service.user'])
                ->latest()
                ->get()
                ->map(function ($interaction) {
                    return $interaction->service;
                })
                ->filter();

            return response()->json([
                'success' => true,
                'data' => [
                    'products' => $produits->values(),
                    'services' => $services->values()
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Erreur liste favoris', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des favoris',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ajoute ou retire un service des favoris de l'utilisateur
     */
    public function toggleServiceFavorite(Request $request, $id): JsonResponse
    {
        try {
            $user = $request->user();
            $service = Service::findOrFail($id);
            $isFavorited = false;

            \DB::transaction(function () use ($user, $service, &$isFavorited) {
                if ($user->hasFavoritedService($service->id)) {
                    // Retirer des favoris
                    $user->removeFavoriteService($service->id);
                    $isFavorited = false;
                } else {
                    // Ajouter aux favoris
                    $user->addFavoriteService($service->id);
                    $isFavorited = true;
                }
            });

            return response()->json([
                'success' => true,
                'is_favorited' => $isFavorited,
                'message' => $isFavorited 
                    ? 'Service ajouté aux favoris' 
                    : 'Service retiré des favoris',
                'favorites_count' => $user->serviceFavorites()->where('type', 'favori')->count()
            ]);

        } catch (\Exception $e) {
            \Log::error('Erreur toggle service favori', [
                'user_id' => $request->user()->id,
                'service_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Ajoute ou retire un produit des favoris de l'utilisateur
     */
    public function toggleProduitFavorite(Request $request, $id): JsonResponse
    {
        try {
            $user = $request->user();
            $produit = Produit::findOrFail($id);
            $isFavorited = false;

            \DB::transaction(function () use ($user, $produit, &$isFavorited) {
                if ($user->hasFavoritedProduit($produit->id)) {
                    // Retirer des favoris
                    $user->removeFavoriteProduit($produit->id);
                    $isFavorited = false;
                } else {
                    // Ajouter aux favoris
                    $user->addFavoriteProduit($produit->id);
                    $isFavorited = true;
                }
            });

            return response()->json([
                'success' => true,
                'is_favorited' => $isFavorited,
                'message' => $isFavorited 
                    ? 'Produit ajouté aux favoris' 
                    : 'Produit retiré des favoris',
                'favorites_count' => $user->produitFavorites()->where('type', 'favori')->count()
            ]);

        } catch (\Exception $e) {
            \Log::error('Erreur toggle produit favori', [
                'user_id' => $request->user()->id,
                'produit_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour des favoris',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
