<?php

namespace App\Http\Controllers;

use App\Models\Service;
use App\Models\ServiceCount;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\Laravel\Facades\Image;

class ServiceUserController extends Controller
{
    // Services de l'user connecté
    public function mesServices()
    {
        try {
            $services = Service::with(['category', 'user'])
                ->where('user_id', auth()->id())
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $services,
                'message' => 'Vos services récupérés avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Créer un nouveau service
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'titre' => 'required|string|max:255',
                'description' => 'required|string',
                'category_id' => 'required|exists:category_services,id',
                'annees_experience' => 'nullable|integer|min:0',
                'competences' => 'nullable|array',
                'competences.*' => 'string',
                'localisation' => 'required|string|max:100',
                'ville' => 'required|string|max:100',
                'photos' => 'required|array',
                'photos.*' => 'image|max:2048'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Données invalides',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Gestion des images
            $images = [];
            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photo) {
                    $filename = time() . '_' . pathinfo($photo->getClientOriginalName(), PATHINFO_FILENAME) . '.jpg';
                    $destinationPath = public_path('storage/services');

                    if (!file_exists($destinationPath)) {
                        mkdir($destinationPath, 0755, true);
                    }

                    // Compression avec Intervention Image
                    $image = Image::read($photo)
                        ->encodeByExtension('jpg', quality: 20);
                    $image->save($destinationPath . '/' . $filename);

                    $images[] = asset('storage/services/' . $filename);
                }
            }

            $service = Service::create([
                'user_id' => auth()->id(),
                'category_id' => $request->category_id,
                'titre' => $request->titre,
                'description' => $request->description,
                'annees_experience' => $request->annees_experience,
                'competences' => $request->competences,
                'localisation' => $request->localisation,
                'ville' => $request->ville,
                'disponibilite' => 'disponible',
                'images' => $images
            ]);

               ServiceCount::create([
            'service_id' => $service->id,
         
        ]);

            return response()->json([
                'success' => true,
                'data' => $service->load(['category', 'user']),
                'message' => 'Service créé avec succès'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la création du service',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Mettre à jour un service
    public function update(Request $request, $id)
    {

          
        try {
            $service = Service::where('user_id', auth()->id())
                ->findOrFail($id);

            $validator = Validator::make($request->all(), [
                'titre' => 'sometimes|required|string|max:255',
                'description' => 'sometimes|required|string',
                'category_id' => 'sometimes|required|exists:category_services,id',
                'annees_experience' => 'nullable|integer|min:0',
                'competences' => 'nullable|array',
                'competences.*' => 'string',
                'localisation' => 'sometimes|required|string|max:100',
                'ville' => 'sometimes|required|string|max:100',
                'disponibilite' => 'sometimes|required|in:disponible,indisponible',
                'photos' => 'nullable|array',
                'photos.*' => 'image|max:2048',
                'old_photos' => 'nullable|array',
                'old_photos.*' => 'string'
            ]);

            
            if ($validator->fails()) {

        //         return response()->json([
        //      'success' => false,
        //      'message' => 'Erreur lors de la mise à jour du service',
        //      'error' => $e->getMessage()
        //  ], 500);
                return response()->json([
                    'success' => false,
                    'message' => 'Données invalides',
                    'errors' => $validator->errors()
                ], 422);

            }

            // Gestion des images
            $oldPhotos = $request->old_photos ?? [];
            $existingImages = $service->images ?? [];

            // Supprimer les images qui ne sont plus dans old_photos
            foreach ($existingImages as $existingImage) {
                if (!in_array($existingImage, $oldPhotos)) {
                    $path = public_path(str_replace(asset(''), '', $existingImage));
                    if (file_exists($path)) {
                        unlink($path);
                    }
                }
            }

            // Ajouter les nouvelles images
            $images = $oldPhotos;

            if ($request->hasFile('photos')) {
                foreach ($request->file('photos') as $photo) {
                    $filename = time() . '_' . pathinfo($photo->getClientOriginalName(), PATHINFO_FILENAME) . '.jpg';
                    $destinationPath = public_path('storage/services');

                    if (!file_exists($destinationPath)) {
                        mkdir($destinationPath, 0755, true);
                    }

                    // Compression avec Intervention Image
                    $image = Image::read($photo)
                        ->encodeByExtension('jpg', quality: 20);
                    $image->save($destinationPath . '/' . $filename);

                    $images[] = asset('storage/services/' . $filename);
                }
            }

            

            $service->update([
                'titre' => $request->titre ?? $service->titre,
                'description' => $request->description ?? $service->description,
                'category_id' => $request->category_id ?? $service->category_id,
                'annees_experience' => $request->annees_experience ?? $service->annees_experience,
                'competences' => $request->competences ?? $service->competences,
                'localisation' => $request->localisation ?? $service->localisation,
                'ville' => $request->ville ?? $service->ville,
                'disponibilite' => $request->disponibilite ?? $service->disponibilite,
                'images' => $images
            ]);

            return response()->json([
                'success' => true,
                'data' => $service->load(['category', 'user']),
                'message' => 'Service mis à jour avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Supprimer un service
    public function destroy($id)
    {
        try {
            $service = Service::where('user_id', auth()->id())
                ->findOrFail($id);

            // Supprimer les images associées
            if ($service->images) {
                foreach ($service->images as $image) {
                    $path = public_path(str_replace(asset(''), '', $image));
                    if (file_exists($path)) {
                        unlink($path);
                    }
                }
            }

            $service->delete();

            return response()->json([
                'success' => true,
                'message' => 'Service supprimé avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du service',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Recherche de services
    public function search(Request $request)
    {
        try {
            $query = Service::with(['category', 'user'])
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

    // Toggle disponibilité
    public function toggleDisponibilite($id)
    {
        try {
            $service = Service::where('user_id', auth()->id())
                ->findOrFail($id);

            $nouvelleDisponibilite = $service->disponibilite === 'disponible' ? 'indisponible' : 'disponible';

            $service->update([
                'disponibilite' => $nouvelleDisponibilite
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'disponibilite' => $nouvelleDisponibilite
                ],
                'message' => 'Disponibilité mise à jour avec succès'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de la disponibilité',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}