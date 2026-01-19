<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

class ProfileController extends Controller
{
    /**
     * Afficher le profil de l'utilisateur connecté
     */
    public function show()
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'Utilisateur non authentifié'], 401);
        }

        $profileData = [
            'id' => $user->id,
            'nom' => $user->nom,
            'email' => $user->email,
            'telephone' => $user->telephone,
            'parrainage_code' => $user->parrainage_code,
            'parrain_id' => $user->parrain_id,
            'created_at' => $user->created_at,
            'role' => $user->role ?? 'user',
            'parrainages' => $this->getParrainages($user->id),
            'total_gains' => $this->calculateTotalGains($user->id),
            'statistiques' => [
                'produits_count' => $user->produits()->count(),
                'services_count' => $user->services()->count(),
                'total_views' => $user->produits()->sum('views_count') ?? 0,
            ]
        ];

        return response()->json($profileData);
    }

    /**
     * Récupérer les détails des parrainages de l'utilisateur
     */
    protected function getParrainages($userId)
    {
        return User::where('parrain_id', $userId)->get()->map(function ($filleul) {
            return [
                'filleul_nom' => $filleul->nom,
                'date_inscription' => $filleul->created_at,
                'has_products' => $filleul->produits()->exists(),
            ];
        })->all();
    }



    protected function calculateTotalGains($userId)
    {
        $filleulsActifs = User::where('parrain_id', $userId)->whereHas('produits')->get();
        $gains = 0;

        foreach ($filleulsActifs as $filleul) {
            $gains += 500; // Bonus de 500 FCFA par filleul actif (ayant des produits)
        }

        return $gains;
    }

    /**
     * Met à jour le mot de passe de l'utilisateur après vérification du mot de passe actuel
     */
    public function updatePassword(Request $request)
    {
        // Validation des champs
        
        
        $request->validate([
            'current_password' => 'required',
            'new_password'     => 'required|min:8',
        ]);

        $user = $request->user(); // Récupération directe de l'utilisateur connecté

        // Vérifier que l'ancien mot de passe est correct
        if (!Hash::check($request->current_password, $user->mot_de_passe)) {
          
            return response()->json(['message'=> 'Le mot de passe actuel est incorrect.'], 404);
            // return response()->json('current_password' , 'Le mot de passe actuel est incorrect.');
        }

        // Mettre à jour le mot de passe
        $user->update([
            'mot_de_passe' => \Hash::make($request->new_password),
        ]);
        return response()->json(['success'=> 'Mot de passe modifié avec succès !']);


    }

    // public function updateProfilePhoto(Request $request)
    // {
    //     $user = $request->user();

    //     $request->validate([
    //         'photo' => 'required|image|max:2048', // Limite à 2 Mo et accepte uniquement les images
    //     ]);

    //     // Supprimer l'ancienne photo si elle existe
    //     if ($user->photo) {
    //         Storage::disk('public')->delete($user->photo);
    //     }

    //     // Stocker la nouvelle photo
    //     $photoPath = $request->file('photo')->store('profile_photos', 'public');
    //     $user->update(['photo' => $photoPath]);

    //     return response()->json([
    //         'message' => 'Photo de profil mise à jour avec succès.',
    //         'photo' => $photoPath,
    //     ], 200);
    // }


    // use Illuminate\Http\Request;
    // use Intervention\Image\Laravel\Facades\Image;

    /**
     * Met à jour la photo de profil, compresse l'image et supprime l'ancienne
     */
    public function updateProfilePhoto(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'photo' => 'required|image|max:2048', // Limite à 2 Mo
        ]);

        // Supprimer l'ancienne photo si elle existe
        if ($user->photo && file_exists(public_path('storage/' . $user->photo))) {
            unlink(public_path('storage/' . $user->photo));
        }

        // Traiter la nouvelle photo avec Intervention Image
        $file = $request->file('photo');
        $filename = time() . '_' . pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) . '.jpg'; // Forcer .jpg
        $destinationPath = public_path('storage/profile_photos');

        // Créer le dossier s'il n'existe pas
        if (!file_exists($destinationPath)) {
            mkdir($destinationPath, 0755, true);
        }

        // Compression et redimensionnement
        $image = Image::read($file)
            // ->resize(300, 300, function ($constraint) {
            //     $constraint->aspectRatio(); // Préserve le ratio
            //     $constraint->upsize(); // Évite l’agrandissement
            // })
            ->encodeByExtension('jpg', quality: 10); // Compression à 75%

        $image->save($destinationPath . '/' . $filename);

        // Enregistrer le chemin relatif en BDD
        $photoPath = 'profile_photos/' . $filename;
        $user->update(['photo' => $photoPath]);

        return response()->json([
            'message' => 'Photo de profil mise à jour avec succès.',
            'photo' => $photoPath, // URL complète pour l’affichage
        ], 200);
    }



    /**
     * Récupère les informations publiques d'un profil utilisateur
     */
    public function publicProfile($id)
    {
        $user = User::findOrFail($id);

        return response()->json([
            'id' => $user->id,
            'nom' => $user->nom,
            'photo' => $user->photo,
            'premium' => $user->premium,
            'created_at'=>$user->created_at,
            'subscription_ends_at' => $user->subscription_ends_at,
            'produits_count' => $user->produits()->count(),
            'services_count' => $user->services()->count(),
            'ville' => $user->ville,
            'telephone' => $user->telephone,
        ]);
    }

    /**
     * Met à jour les informations du profil de l'utilisateur connecté
     */
    public function updateProfile(Request $request)
    {

        $user = $request->user();
$user->update($request->all());
      return response()->json([
            'user' => $user->fresh()
        ], 200);
  
    }
    
}