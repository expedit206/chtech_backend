<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\Request;
use App\Models\CategoryService;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ServiceController;

class CategoryServiceController extends Controller
{
    public function index()
    { 
        $categories = CategoryService::orderBy('nom', 'asc')->get();
        return response()->json(['categoryServices' => $categories]);
    }

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
    }}
