<?php

namespace App\Http\Controllers;

use Ramsey\Uuid\Uuid;
use App\Models\Produit;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\Revente;

class ReventeController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        ; // Assurez-vous que l'utilisateur a un user

        // return response()->json([
        //     'sent_reventes' =>'user',
        // ]);
        // Reventes envoyers (où l'utilisateur est le user demandeur)
        $sentReventes = Revente::with('produit')
            ->where('revendeur_id', $user->id)
            ->orderBy('created_at', 'desc')->get();

        // Reventes reçues (où l'utilisateur est le propriétaire du produit)
        $receivedReventes = Revente::with(['produit' => function ($query) use ($user) {
            $query->whereHas('user', function ($query) use ($user) {
                $query->where('id', $user->id);
            });
        }])->whereNotIn('id', function ($query) use ($user) {
            $query->select('id')
                  ->from('reventes')
                  ->where('revendeur_id', $user->id);
        })->orderBy('created_at', 'desc')->get();

        return response()->json([
            'sent_reventes' => $sentReventes->load('revendeur'),
            'received_reventes' => $receivedReventes->load('revendeur'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'produit_id' => 'required|uuid|exists:produits,id',
            'prix_revente' => 'required|numeric|min:0',
        ]);
        $user = $request->user();
         // Assurez-vous que l'utilisateur a un profil user
        $produit = Produit::findOrFail($data['produit_id']);

        $existRevente = Revente::where('revendeur_id', $user->id)
        ->where('produit_id', $produit->id)
        ->first();
        if ($existRevente) {
            return response()->json(['message' => 'Vous avez deja une revente pour ce produit'], 422);
        }
        if (!$user) {
            return response()->json(['message' => 'Vous devez avoir un profil user pour revendre'], 422);
        }
        if ($user->id === $produit->user_id) {
            return response()->json(['message' => 'Vous ne pouvez pas revendre sur votre propre produit'], 422);
        }
        if (!$produit->revendable) {
            return response()->json(['message' => 'Ce produit n’est pas ouvert à la revente'], 422);
        }
        if ($data['prix_revente'] < $produit->prix + ($produit->marge_min ?? 0)) {
            return response()->json(['message' => 'Le prix de revente est trop bas'], 422);
        }

        $revente = Revente::create([
            'revendeur_id' => $user->id,
            'produit_id' => $data['produit_id'],
            'prix_revente' => $data['prix_revente'],
            'statut' => 'en_attente',
        ]);

            try {
          
            // charger les relations utiles
            $produit->loadMissing('user.user');
            $owner = $produit->user->user ?? null;

            // $revente->load(['produit', 'user']);
            if ($owner) {
                $notificationService = app()->make(\App\Services\NotificationService::class);
                $template = \App\Services\NotificationTemplateService::reventeRequested($revente);

                $deviceToken = $owner->deviceTokens?->where('is_active', true)->pluck('device_token')->first() ?? null;
                if ($deviceToken) {
                    // signature attendue : (deviceToken, notification, data)
                    $notificationService->sendToDevice($deviceToken, $template['notification'], $template['data']);
                    \Illuminate\Support\Facades\Log::error('Envoi notification reventeRequested reussi'.  $owner );
                }else{
                \Illuminate\Support\Facades\Log::error('pas de token');

                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Envoi notification reventeRequested échoué : ' . $e, ['revente_id' => $revente->id]);
        }

        return response()->json(['message' => 'Demande de revente envoyée', 'revente' => $revente,
    'success'=> true]);
    }
//verifié si il a deja collaboré le produit

    public function status(Request $request , $id){
           $user = $request->user();
         // Assurez-vous que l'utilisateur a un profil user
        $produit = Produit::findOrFail($id);

       
        $existRevente = Revente::where('revendeur_id', $user->id)
        ->where('produit_id', $produit->id)
        ->first();
        if ($existRevente) {
            return response()->json(['message' => 'Vous avez deja une revente pour ce produit','revendu'=>true]);
        }

        return response()->json(['revendu'=>false]);

    }
    public function update(Request $request, $id)
    {
        $request->validate([
            'statut' => 'required|in:valider,refuser',
        ]);

        try{

  
        $revente = Revente::findOrFail($id);
        $produit = $revente->produit->load('user');
        $user = $produit->user;
        
        if ($user->id !== auth()->id()) {
            return response()->json(['message' => 'Accès non autorisé'], 403);
        }
        

        if ($request->statut === 'valider') {
            // Cloner le produit
            $clonedProduit = $produit->replicate();
            $clonedProduit->id = Str::uuid(); // ID du user qui accepte
            // return response()->json(['message' =>  auth()->id()], 500);
            $clonedProduit->user_id = $revente->revendeur->id; 
            
            $clonedProduit->prix = $revente->prix_revente; // Nouveau prix
            $clonedProduit->original_user_id = $produit->user_id; // ID du user original
            $clonedProduit->revendable = false; // Non revendable
            $clonedProduit->save();
        }


        $revente->statut = $request->statut;
        $revente->save();

        return response()->json(['message' => 'Revente mise à jour', 'revente' => $revente]);
              } catch(\Exception $e ){
            return response()->json(['message'=>$e->getMessage()],500);
        }

    }
}