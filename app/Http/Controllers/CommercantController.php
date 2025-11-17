<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Produit;
use App\Models\Commercant;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
// use Intervention\Image\Image;
use Intervention\Image\Format;
use App\Models\ProductFavorite;
use App\Models\ParrainageNiveau;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

class CommercantController extends Controller
{
   

    // use Intervention\Image\Facades\Image;

    // use Illuminate\Http\Request;
    // use Intervention\Image\Laravel\Facades\Image;

   
  

  

      public function destroyProduit(Request $request, $id)
{
    $user = $request->user()->load('commercant');
    $produit = Produit::where('commercant_id', $user->commercant->id)->findOrFail($id);

    if (!$user->commercant || $produit->commercant_id !== $user->commercant->id) {
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

    public function profil(Request $request)
    {
        $request->user()->load('commercant',);
        $commercant = $request->user()->commercant;
        return response()->json(['commercant' => '$commercant']);
    }


public function show($id)
{
    $commercant = Commercant::with([
        'produits' => function($query) {
            $query->with(['counts'])
                  ->with('category');
        }
    ])->findOrFail($id);

    $averageRating = $commercant->average_rating;
    $voteCount = $commercant->ratings()->count();



    return response()->json([
        'commercant' => $commercant,
        'vote_count' => $voteCount,
        'average_rating' => $averageRating,
     
    ]);
}

    

    public function rate(Request $request, $id)
    {
        $commercant = Commercant::findOrFail($id);
        $user = $request->user();

        // Vérifier si l'utilisateur a déjà noté
        $existingRating = $commercant->ratings()->where('user_id', $user->id)->first();
        if ($existingRating) {
            return response()->json(['message' => 'Vous avez déjà noté ce commerçant.'], 400);
        }

        $request->validate([
            'rating' => 'required|integer|between:1,5',
        ]);

        // return response()->json(['message' => $request->all()]);


        $commercant->ratings()->create([
            'user_id' => $user->id,
            'commercant_id' => $id,
            'rating' => $request->rating,
        ]);

        $averageRating = $commercant->average_rating;

        return response()->json([
            'message' => 'Note enregistrée avec succès.',
            'average_rating' => $averageRating,
        ]);
    }

    public function updateProfil(Request $request)
    {
        // Charger l'utilisateur authentifié avec sa relation commerçant
        $user = $request->user()->load('commercant');

        // Vérifier si l'utilisateur a un profil commerçant
        if (!$user->commercant) {
            return response()->json(['message' => 'Profil commerçant non trouvé.'], 404);
        }

        // Validation des données
        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'description' => 'nullable|string',
            'email' => 'nullable|email|max:255',
            'telephone' => 'required|string|max:20',
            'ville' => 'nullable|string',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // Limite à 2 Mo
        ]);

        // Préparer les données pour la mise à jour
        $data = [
            'nom' => $validated['nom'],
            'description' => $validated['description'],
            'email' => $validated['email'],
            'telephone' => $validated['telephone'],
            'ville' => $validated['ville'],
        ];

        // Gérer le téléchargement du logo si présent
        if ($request->hasFile('logo')) {
            // Supprimer l'ancien logo si existant
            if ($user->commercant->logo && file_exists(public_path('storage/' . $user->commercant->logo))) {
                unlink(public_path('storage/' . $user->commercant->logo));
            }

            // Traiter la nouvelle photo avec Intervention Image
            $file = $request->file('logo');
            $filename = time() . '_' . pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) . '.jpg'; // Forcer .jpg
            $destinationPath = public_path('storage/commercant/logos');

            // Créer le dossier s'il n'existe pas
            if (!file_exists($destinationPath)) {
                mkdir($destinationPath, 0755, true);
            }

            // Compression et redimensionnement
            $image = Image::read($file)
             
                ->encodeByExtension('jpg', quality: 15); // Compression à 75%

            $image->save($destinationPath . '/' . $filename);

            // Enregistrer le chemin relatif en BDD
            $data['logo'] = 'commercant/logos/' . $filename;
        }

        // Mettre à jour le profil du commerçant
        $user->commercant->update($data);

        // Recharger la relation pour retourner les données mises à jour
        $updatedCommercant = $user->commercant->fresh();

        return response()->json([
            'success' => true,
            'commercant' => $updatedCommercant,
        ], 200);
    }

    // app/Http/Controllers/CommercantController.php
 
    public function create(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'nom' => 'required|string|max:255',
            'ville' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'logo' => 'nullable|string|max:255',
            'email' => 'required|email|max:255',
            'telephone' => 'required|string|max:13|unique:commercants,telephone',

            //telephone pour cameroun

        ]);

        // Generate a 6-digit verification code
        $verificationCode = Str::random(6);

        // Create the merchant account (not active until verified)
        $commercant = Commercant::create([
            'user_id' => $user->id,
            'nom' => $validated['nom'],
            'ville' => $validated['ville'],
            'telephone' => $validated['telephone'],
            'description' => $validated['description'] ?? null,
            'logo' => $validated['logo'] ?? null,
            'email' => $validated['email'],
            'verification_code' => $verificationCode,
        ]);

        // Trigger email sending (we'll use EmailJS from frontend, but prepare data)
        return response()->json([
            'message' => 'Compte créé, veuillez vérifier votre email',
            'commercant' => $commercant,
            'verification_code' => $verificationCode // Temporarily return for frontend to send email
        ], 201);
    }

    public function verifyEmail(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
        ]);

        $commercant = Commercant::where('email', $validated['email'])
            ->where('verification_code', $validated['code'])
            ->first();

        if (!$commercant) {
            return response()->json(['message' => 'Code ou email invalide'], 400);
        }

        // Mark email as verified
        $commercant->update([
            'verification_code' => null,
            'email_verified_at' => now(),
        ]);

        // Handle referral if applicable
        if ($commercant->user->parrain_id) {
            $this->updateParrainage($commercant->user->parrain_id);
        }

        return response()->json(['message' => 'Email vérifié avec succès'], 200);
    }

    // app/Http/Controllers/CommercantController.php
    public function resendVerification(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);

        $commercant = Commercant::where('email', $validated['email'])->first();

        if (!$commercant || $commercant->email_verified_at) {
            return response()->json(['message' => 'Email déjà vérifié ou introuvable'], 400);
        }

        $newCode = Str::random(6);
        $commercant->update(['verification_code' => $newCode]);

        return response()->json([
            'message' => 'Nouveau code envoyé',
            'verification_code' => $newCode,
        ], 200);
    }

    private function updateParrainage($parrain_id)
    {
        $parrain = User::with('niveaux_users')->find($parrain_id);
        if (!$parrain) {
            return;
        }

        // Compter uniquement les filleuls commerçants
        $filleuls_commercants = User::where('parrain_id', $parrain_id)
            ->whereHas('commercant')
            ->count();

        // Récupérer ou créer l'entrée dans niveaux_users
        $niveau_actuel = $parrain->niveaux_users()->where('statut', 'actif')->latest('date_atteinte')->first();
        $niveau_id = $this->determinerNiveau($filleuls_commercants);

        if (!$niveau_actuel || $niveau_actuel->niveau_id != $niveau_id) {
            $niveau = ParrainageNiveau::find($niveau_id);
            $niveau_user = $parrain->niveaux_users()->create([

                'user_id' => $parrain->id,
                'niveau_id' => $niveau_id,
                'date_atteinte' => now(),
                'jetons_attribues' => $niveau->jetons_bonus,
                'nombre_filleuls_actuels' => $filleuls_commercants,
            ]);
            // return response()->json(['message' => $filleuls_commercants]);

            // Mettre à jour les jetons totaux
            $parrain->increment('jetons', $niveau->jetons_bonus);
        } else {
            $niveau_actuel->update(['nombre_filleuls_actuels' => $filleuls_commercants]);
        }
        $parrain->jetons+=1;
        $parrain->save();
        
    }

    private function determinerNiveau($filleuls_commercants)
    {
        $niveaux = ParrainageNiveau::orderBy('filleuls_requis', 'desc')->get();
        foreach ($niveaux as $niveau) {
            if ($filleuls_commercants >= $niveau->filleuls_requis) {
                return $niveau->id;
            }
        }
        return 1; // Niveau par défaut (Initié)
    }

    public function getParrainage(Request $request)
    {
        $user = $request->user();
        $filleuls_commercants = User::where('parrain_id', $user->id)->whereHas('commercant')->count();
        $niveau = $user->niveaux_users()->where('statut', 'actif')->with('niveau')->latest('date_atteinte')->first();

        $prochain_niveau = ParrainageNiveau::where('filleuls_requis', '>', ($niveau ? $niveau->nombre_filleuls_actuels : 0))
            ->orderBy('filleuls_requis', 'asc')
            ->first();

        return response()->json([
            'niveau' => $niveau ? $niveau->niveau : ParrainageNiveau::find(1),
            'jetons' => $user->jetons,
            'avantages' => $niveau ? json_decode($niveau->niveau->avantages) : [],
            'filleuls_commercants' => $filleuls_commercants,
            'prochain_seuil' => $prochain_niveau ? $prochain_niveau->filleuls_requis : 1000,
        ]);
    }
}