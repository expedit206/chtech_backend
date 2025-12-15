<?php

// app/Http/Controllers/Api/InteractionController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ProduitInteraction;
use App\Models\ServiceInteraction;
use App\Http\Controllers\Controller;
use App\Services\InteractionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class InteractionController extends Controller
{

        protected $interactionService;

    public function __construct(InteractionService $interactionService)
    {
        $this->interactionService = $interactionService;
    }


    /**
     * Enregistrer une interaction
     */
    public function store(Request $request)
    {

        $validator = $request->validate([
            'content_id' => 'required|string',
            'content_type' => 'required|in:produit,service',
            'type' => 'required|in:clic,favori,contact,partage',
            'metadata' => 'nullable|array'
        ]);

        try {
            $model = $request->content_type=='produit'? ProduitInteraction::class : ServiceInteraction::class;
            $alreadyFavorited = $model::where('user_id', auth()->id())
            ->where($request->content_type.'_id', $request->content_id )
            ->where('type', 'favori')->exists();
            $interaction = $this->interactionService->recordInteraction(
                $request->content_id,
                $request->content_type,
                $request->type,
                $request->metadata
            );


            if( $request->type== 'favori'   && $alreadyFavorited){
                
                            return response()->json([
                                'success' => true,
                                'message' => 'Interaction enregistrée',
                                'data' => $interaction
                            ]);
            }
            // Mettre à jour le compteur sur le produit/service si nécessaire
                $this->updateContentInteractionCount(
                    $request->content_id,
                    $request->content_type,
                    $request->type
                );

            return response()->json([
                'success' => true,
                'message' => 'Interaction enregistrée',
                'data' => $interaction
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Récupérer les interactions d'un utilisateur
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Non authentifié'
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'content_type' => 'required|in:produit,service',
            'type' => 'nullable|in:clic,favori,contact,partage',
            'limit' => 'nullable|integer|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        $interactions = $this->interactionService->getUserInteractions(
            $request->content_type,
            $user->id,
            $request->type,
            $request->limit ?? 50
        );

        return response()->json([
            'success' => true,
            'data' => $interactions,
            'count' => $interactions->count()
        ]);
    }

    /**
     * Récupérer les catégories préférées
     */
    public function preferredCategories()
    {
        $user = Auth::user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Non authentifié'
            ], 401);
        }

        $categories = $this->interactionService->getPreferredCategories($user->id);

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    /**
     * Mettre à jour les compteurs d'interaction sur le contenu
     */
    private function updateContentInteractionCount($contentId, $contentType, $interactionType)
    {
        $model = $contentType === 'produit' 
            ? \App\Models\Produit::class 
            : \App\Models\Service::class;

        $content = $model::find($contentId);
        if (!$content) return;

        // Incrémenter le compteur approprié
        switch ($interactionType) {
            case 'favori':
                $content->counts()->increment('favorites_count');
                break;
            case 'contact':
                $content->counts()->increment('contacts_count');
                break;
            case 'clic':
                $content->counts()->increment('clics_count');
                break;
            case 'partage':
                $content->counts()->increment('partages_count');
                break;
        }
    }
}