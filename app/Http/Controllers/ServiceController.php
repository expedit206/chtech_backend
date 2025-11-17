<?php
// app/Http/Controllers/ServiceController.php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\CategorieService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\Laravel\Facades\Image;

class ServiceController extends Controller
{
    // Liste des services avec filtres
    public function index(Request $request)
    {
        try {
            $query = Service::with(['categorie', 'user'])
                ->disponible();

            // Filtres
            if ($request->has('categorie')) {
                $query->byCategorie($request->categorie);
            }

            if ($request->has('ville')) {
                $query->byVille($request->ville);
            }

            if ($request->has('type_service')) {
                $query->where('type_service', $request->type_service);
            }

            if ($request->has('prix_min')) {
                $query->where('prix', '>=', $request->prix_min);
            }

            if ($request->has('prix_max')) {
                $query->where('prix', '<=', $request->prix_max);
            }

            // Tri
            $sort = $request->get('sort', 'date_publication');
            $order = $request->get('order', 'desc');

            $query->orderBy($sort, $order);

            $services = $query->paginate($request->get('per_page', 12));

            return response()->json([
                'success' => true,
                'data' => $services,
                'message' => 'Services récupérés avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des services',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Afficher un service spécifique
    public function show($id)
    {
        try {
            $service = Service::with(['categorie', 'user', 'avis.user'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $service,
                'message' => 'Service récupéré avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Service non trouvé',
                'error' => $e->getMessage()
            ], 404);
        }
    }


      public function search(Request $request)
    {
        try {
            $query = Service::with(['categorie', 'user'])
                ->where('disponibilite', 'disponible');

            if ($request->has('q')) {
                $searchTerm = $request->q;
                $query->where(function($q) use ($searchTerm) {
                    $q->where('titre', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('description', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('competences', 'LIKE', "%{$searchTerm}%");
                });
            }

            $services = $query->paginate($request->get('per_page', 12));

            return response()->json([
                'success' => true,
                'data' => $services,
                'message' => 'Résultats de recherche récupérés avec succès'
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