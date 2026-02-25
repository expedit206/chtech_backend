<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\Request;
use App\Models\CategoryService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ServiceController;

class CategoryServiceController extends Controller
{
    /**
     * Liste toutes les catégories de services ordonnées par nom
     */
    public function index()
    {
        $categories = \Illuminate\Support\Facades\Cache::remember('category_services_list', 86400, function () {
            return CategoryService::orderBy('nom', 'asc')->get();
        });

        return response()->json(['categoryServices' => $categories]);
    }

    /**
     * Récupère les services liés à la catégorie d'un service spécifique
     */
    public function relatedProduct(Service $produit, Request $request)
    {
        $categoryId = $request->query('category_id', $produit->category_id); // Utilise la catégorie du produit si non spécifiée
        $user = $request->user();

        // Créer une nouvelle requête avec les paramètres nécessaires
        $customRequest = Request::create('', '', [
            'category' => $categoryId,
        ]);
        $request->merge([
            'category' => $categoryId,

        ]);

        // Instancier ServiceController et appeler index
        $produitController = new ServiceController();
        $response = $produitController->index($request);

        // Décoder la réponse JSON
        $produits = json_decode($response->getContent(), true);

        // Si paginé, extraire les résultats (produits)
        $produits = $produits['data'] ?? $produits;




        return response()->json(['produits' => $produits]);
    }
}
