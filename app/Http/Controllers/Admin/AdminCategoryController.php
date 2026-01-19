<?php

namespace App\Http\Controllers\Admin;

use App\Models\CategoryProduit;
use App\Models\CategoryService;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Str;

class AdminCategoryController extends Controller
{
    /**
     * Liste toutes les catégories (produits et services)
     */
    public function index()
    {
        return response()->json([
            'products' => CategoryProduit::orderBy('nom')->get(),
            'services' => CategoryService::orderBy('nom')->get()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'type' => 'required|string|in:product,service',
            'image' => 'nullable|image|max:2048'
        ]);

        $imagePath = null;
        if ($request->hasFile('image')) {
            $photo = $request->file('image');
            $filename = time() . '_' . Str::uuid() . '.' . $photo->getClientOriginalExtension();
            $destinationPath = public_path('storage/categories');

            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0755, true);
            }

            $photo->move($destinationPath, $filename);
            $imagePath = asset('storage/categories/' . $filename);
        }

        if ($validated['type'] === 'product') {
            $category = CategoryProduit::create([
                'id' => Str::uuid(),
                'nom' => $validated['nom'],
                'image' => $imagePath
            ]);
        } else {
            $category = CategoryService::create([
                'id' => Str::uuid(),
                'nom' => $validated['nom'],
                'image' => $imagePath
            ]);
        }

        return response()->json([
            'message' => 'Catégorie créée avec succès',
            'category' => $category
        ]);
    }

    /**
     * Met à jour une catégorie
     */
    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'type' => 'required|string|in:product,service',
            'image' => 'nullable|image|max:2048'
        ]);

        if ($validated['type'] === 'product') {
            $category = CategoryProduit::findOrFail($id);
        } else {
            $category = CategoryService::findOrFail($id);
        }

        $imagePath = $category->image;
        if ($request->hasFile('image')) {
            // Supprimer l'ancienne image si elle existe
            if ($imagePath) {
                $oldPath = public_path(str_replace(asset(''), '', $imagePath));
                if (file_exists($oldPath)) {
                    unlink($oldPath);
                }
            }

            $photo = $request->file('image');
            $filename = time() . '_' . Str::uuid() . '.' . $photo->getClientOriginalExtension();
            $destinationPath = public_path('storage/categories');

            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0755, true);
            }

            $photo->move($destinationPath, $filename);
            $imagePath = asset('storage/categories/' . $filename);
        }

        $category->update([
            'nom' => $validated['nom'],
            'image' => $imagePath
        ]);

        return response()->json([
            'message' => 'Catégorie mise à jour avec succès',
            'category' => $category
        ]);
    }

    /**
     * Supprime une catégorie
     */
    public function destroy(Request $request, $id)
    {
        $type = $request->query('type');
        
        if ($type === 'product') {
            $category = CategoryProduit::findOrFail($id);
        } else {
            $category = CategoryService::findOrFail($id);
        }

        $category->delete();

        return response()->json(['message' => 'Catégorie supprimée avec succès']);
    }
}
