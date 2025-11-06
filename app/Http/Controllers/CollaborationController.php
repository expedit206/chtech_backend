<?php

namespace App\Http\Controllers;

use Ramsey\Uuid\Uuid;
use App\Models\Produit;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\Collaboration;

class CollaborationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $commercant = $user->commercant; // Assurez-vous que l'utilisateur a un commercant

        // return response()->json([
        //     'sent_collaborations' =>'commercant',
        // ]);
        // Collaborations envoyers (où l'utilisateur est le commerçant demandeur)
        $sentCollaborations = Collaboration::with('produit')
            ->where('commercant_id', $commercant->id)
            ->orderBy('created_at', 'desc')->get();

        // Collaborations reçues (où l'utilisateur est le propriétaire du produit)
        $receivedCollaborations = Collaboration::with(['produit' => function ($query) use ($user) {
            $query->whereHas('commercant', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            });
        }])->whereNotIn('id', function ($query) use ($commercant) {
            $query->select('id')
                  ->from('collaborations')
                  ->where('commercant_id', $commercant->id);
        })->orderBy('created_at', 'desc')->get();

        return response()->json([
            'sent_collaborations' => $sentCollaborations->load('commercant'),
            'received_collaborations' => $receivedCollaborations->load('commercant'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'produit_id' => 'required|uuid|exists:produits,id',
            'prix_revente' => 'required|numeric|min:0',
        ]);
        $user = $request->user();
        $commercant = $user->commercant; // Assurez-vous que l'utilisateur a un profil commerçant
        $produit = Produit::findOrFail($data['produit_id']);

        if (!$commercant) {
            return response()->json(['message' => 'Vous devez avoir un profil commerçant pour collaborer'], 422);
        }
        if ($commercant->id === $produit->commercant_id) {
            return response()->json(['message' => 'Vous ne pouvez pas collaborer sur votre propre produit'], 422);
        }
        if (!$produit->collaboratif) {
            return response()->json(['message' => 'Ce produit n’est pas ouvert à la collaboration'], 422);
        }
        if ($data['prix_revente'] < $produit->prix + ($produit->marge_min ?? 0)) {
            return response()->json(['message' => 'Le prix de revente est trop bas'], 422);
        }

        $collaboration = Collaboration::create([
            'commercant_id' => $commercant->id,
            'produit_id' => $data['produit_id'],
            'prix_revente' => $data['prix_revente'],
            'statut' => 'en_attente',
        ]);

            try {
          
            // charger les relations utiles
            $produit->loadMissing('commercant.user');
            $owner = $produit->commercant->user ?? null;

            // $collaboration->load(['produit', 'commercant']);
            if ($owner) {
                $notificationService = app()->make(\App\Services\NotificationService::class);
                $template = \App\Services\NotificationTemplateService::collaborationRequested($collaboration);

                $deviceToken = $owner->deviceTokens?->where('is_active', true)->pluck('device_token')->first() ?? null;
                if ($deviceToken) {
                    // signature attendue : (deviceToken, notification, data)
                    $notificationService->sendToDevice($deviceToken, $template['notification'], $template['data']);
                    \Illuminate\Support\Facades\Log::error('Envoi notification collaborationRequested reussi'.  $owner );
                }else{
                \Illuminate\Support\Facades\Log::error('pas de token');

                }
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Envoi notification collaborationRequested échoué : ' . $e, ['collaboration_id' => $collaboration->id]);
        }

        return response()->json(['message' => 'Demande de collaboration envoyée', 'collaboration' => $collaboration]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'statut' => 'required|in:valider,refuser',
        ]);

        $collaboration = Collaboration::findOrFail($id);
        $produit = $collaboration->produit;
        $commercant = $produit->commercant;

        if ($commercant->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Accès non autorisé'], 403);
        }


        if ($request->statut === 'valider') {
            // Cloner le produit
            $clonedProduit = $produit->replicate();
            $clonedProduit->id = Str::uuid(); // ID du commerçant qui accepte
            $clonedProduit->commercant_id = $collaboration->commercant->id; 

            $clonedProduit->prix = $collaboration->prix_revente; // Nouveau prix
            $clonedProduit->original_commercant_id = $produit->commercant_id; // ID du commerçant original
            $clonedProduit->collaboratif = false; // Non collaboratif
            $clonedProduit->save();
        }


        $collaboration->statut = $request->statut;
        $collaboration->save();

        return response()->json(['message' => 'Collaboration mise à jour', 'collaboration' => $collaboration]);
    }
}