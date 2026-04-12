<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Promotion;
use App\Models\Produit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PromotionController extends Controller
{
    private const COST_PER_CLICK = 0.2; // 0.2 jetons par clic
    
    // Créer une promotion simplifié    /**
     * Crée une nouvelle campagne de promotion (MUTED)
     */
    public function create(Request $request)
    {
        return response()->json([
            'success' => false,
            'message' => 'Cette fonctionnalité est temporairement désactivée.'
        ], 403);
    }
    
    /**
     * Arrête une promotion active (MUTED)
     */
    public function stop($promotionId)
    {
        return response()->json([
            'success' => false,
            'message' => 'Cette fonctionnalité est temporairement désactivée.'
        ], 403);
    }
    
    /**
     * Récupère la promotion active (MUTED)
     */
    public function getActive($productId)
    {
        return response()->json([
            'success' => true,
            'promotion' => null
        ]);
    }
}