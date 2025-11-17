<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Produit;
use Illuminate\Support\Str;
use App\Http\Controllers\Controller;
use Intervention\Image\Laravel\Facades\Image;
class ProduitUserController extends Controller
{

     public function produits(Request $request)
    {
        $user = $request->user();
        // return response()->json($);
        if (!$user) {
            return response()->json(['message' => 'Accès réservé aux commerçants'], 403);
        }

        $produits = Produit::where('user_id', $user->id)
            ->with('category')
            ->withCount('favorites') // Charger le nombre de favoris
            ->withCount('views')    // Charger le nombre de vues
            ->orderBy('created_at', 'desc')    // Charger le nombre de vues
            ->get();
        // return response()->json(['produits' => 'produits']);
        return response()->json(['produits' => $produits]);
    }
    

     public function storeProduit(Request $request)
    {
        $user = $request->user();
     

        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'description' => 'nullable|string',
            'prix' => 'required|numeric|min:0',
            'photos' => 'required|array',
            'photos.*' => 'image|max:2048',
            'categoryProduit_id' => 'required|exists:category_produits,id',
            'revendable' => 'required',
            'condition' => 'string|required',
            'marge_min' => 'nullable|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'ville' => 'nullable|string',
        ]);

        $photos = [];
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $filename = time() . '_' . pathinfo($photo->getClientOriginalName(), PATHINFO_FILENAME) . '.jpg';
                $destinationPath = public_path('storage/produits');

                if (!file_exists($destinationPath)) {
                    mkdir($destinationPath, 0755, true);
                }

                // Compression avec redimensionnement, suppression des métadonnées, puis encodage
                $image = Image::read($photo)
                    // ->resize(1200, 600, function ($constraint) {
                    //     $constraint->aspectRatio();
                    //     $constraint->upsize();
                    // })
                    ->encodeByExtension('jpg', quality: 20);
                $image->save($destinationPath . '/' . $filename);

                $photos[] = asset('storage/produits/' . $filename);
            }
        }

        $produit = Produit::create([
            'id' => \Illuminate\Support\Str::uuid(),
            'user_id' => $user->id,
            'category_produits_id' => $validated['categoryProduit_id'],
            'nom' => $validated['nom'],
            'description' => $validated['description'],
            'prix' => $validated['prix'],
            'photos' => $photos,
            'revendable' => $validated['revendable'] == '0' ? 0 : 1,
            'marge_revente_min' => $validated['marge_min'] ?? null,
            'quantite' => $validated['stock'],
            'ville' => $validated['ville'] ?? 'aucun',
        ]);

        return response()->json(['produit' => $produit], 201);
    }


public function destroyProduit(Request $request, $id)
{
    $user = $request->user();
    $produit = Produit::where('user_id', $user->id)->findOrFail($id);

    if (!$user || $produit->user_id !== $user->id) {
        return response()->json(['message' => 'Accès non autorisé'], 403);
    }

    // Supprimer les photos associées
    if ($produit->photos) {
        foreach ($produit->photos as $photo) {
            $path = public_path(str_replace(asset(''), '', $photo));
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    $produit->delete();

    return response()->json(['message' => 'Produit supprimé avec succès'], 200);
}

  public function updateProduit(Request $request, $id)
    {
        $user = $request->user();
        $produit = Produit::where('user_id', $user->id)->findOrFail($id);

        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'description' => 'nullable|string',
            'prix' => 'required|numeric|min:0',
            'photos' => 'array',
            'photos.*' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'old_photos' => 'nullable|array',
            'old_photos.*' => 'nullable|string',
            'categoryProduit_id' => 'required|exists:category_produits,id',
            'condition' => 'string|required',
            'revendable' => 'boolean',
            'marge_min' => 'nullable|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'ville' => 'nullable|string',
        ]);

        // Photos gardées par l’utilisateur
        $oldPhotos = $validated['old_photos'] ?? [];

        // Anciennes photos en BDD
        $existingPhotos = $produit->photos ?? [];

        // Supprimer celles qui ne sont plus dans old_photos
        foreach ($existingPhotos as $oldPhoto) {
            if (!in_array($oldPhoto, $oldPhotos)) {
                $path = public_path(str_replace(asset(''), '', $oldPhoto));
                if (file_exists($path)) {
                    unlink($path);
                }
            }
        }

        // Ajouter les nouvelles
        $photos = $oldPhotos; // on garde celles restantes
        
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $filename = time() . '_' . $photo->getClientOriginalName();
                $photo->move(public_path('storage/produits'), $filename);
                
                $photos[] = asset('storage/produits/' . $filename);
            }
        }
        
        // return response()->json(['produit' => $request->hasFile('photos')], 200);
        // Mise à jour
        $produit->update([
            'nom' => $validated['nom'],
            'description' => $validated['description'],
            'prix' => $validated['prix'],
            'photos' => $photos,
            'condition'=>$validated['condition'],
            'category_produits_id' => $validated['categoryProduit_id'],
            'revendable' => $validated['revendable'] ?? false,
            'marge_revente_min' => $validated['marge_min'] ?? null,
            'quantite' => $validated['stock'],
            'ville' => $validated['ville'] ?? 'aucun',
        ]);

        return response()->json(['produit' => $produit], 200);
    }

}
