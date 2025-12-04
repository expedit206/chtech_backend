<?php

namespace App\Http\Controllers;

use App\Models\Produit;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;

class FavoriteController extends Controller
{
 
    
    public function toggleServiceFavorite(Request $request, $id): JsonResponse
    {
        try {
            $user = $request->user();
            $service = Service::findOrFail($id);
            // $isFavorited

            \DB::transaction(function () use ($user, $service) {
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
            
        
            $isFavorited = $user->hasFavoritedService($service->id);

            return response()->json([
                'success' => true,
                'is_favorited' => $isFavorited,
                'message' => $isFavorited 
                    ? 'Service ajouté aux favoris' 
                    : 'Service retiré des favoris',
                'favorites_count' => $user->ServiceFavorites()->count()
            ]);

        } catch (\Exception $e) {
            \Log::error('Erreur toggle service favori', [
                'user_id' => $request->user()->id,
                'service_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour des favoris',
                'error' => $e->getMessage()
            ], 500);
        }
   
    }



    public function toggleProduitFavorite(Request $request, $id): JsonResponse
    {
        try {
            $user = $request->user();
            $produit = Produit::findOrFail($id);
            // $isFavorited

            \DB::transaction(function () use ($user, $produit) {
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
            
        
            $isFavorited = $user->hasFavoritedProduit($produit->id);

            return response()->json([
                'success' => true,
                'is_favorited' => $isFavorited,
                'message' => $isFavorited 
                    ? 'Produit ajouté aux favoris' 
                    : 'Produit retiré des favoris',
                'favorites_count' => $user->produitFavorites()->count()
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
