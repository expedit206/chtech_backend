<?php

namespace App\Http\Controllers\Admin;

use App\Models\Produit;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class AdminProductController extends Controller
{
    /**
     * Liste tous les produits avec pagination et filtres
     */
    public function index(Request $request)
    {
        $query = Produit::with(['user', 'category']);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('nom', 'like', "%{$search}%");
        }

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        $produits = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($produits);
    }

    /**
     * Active/Désactive un produit
     */
    public function toggleStatus($id)
    {
        $produit = Produit::findOrFail($id);
        $produit->update(['est_actif' => !$produit->est_actif]);

        return response()->json([
            'message' => $produit->est_actif ? 'Produit activé' : 'Produit désactivé',
            'produit' => $produit
        ]);
    }

    /**
     * Supprime un produit
     */
    public function destroy($id)
    {
        $produit = Produit::findOrFail($id);
        $produit->delete();

        return response()->json(['message' => 'Produit supprimé avec succès']);
    }
}
