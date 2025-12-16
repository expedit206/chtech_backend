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
    
    // Créer une promotion simplifiée
    public function create(Request $request)
    {
       
        $request->validate([
            'produit_id' => 'required|exists:produits,id',
            'total_clicks' => 'required|integer|min:10|max:10000',
        ]);
        
        $user = auth()->user();
        $produit = Produit::findOrFail($request->produit_id);
        
        // Vérifier la propriété
        if ($produit->user_id !== $user->id) {
            return response()->json(['message' => 'Accès non autorisé'], 403);
        }
        
        // Calculer le coût total (total_clicks * 0.2)
        $totalCost = $request->total_clicks * self::COST_PER_CLICK;
        
        // Vérifier le solde
        if ($user->jetons < $totalCost) {
            return response()->json(['message' => 'Jetons insuffisants'], 400);
        }
        
        DB::beginTransaction();
        
        try {
            // Déduire les jetons
            $user->jetons -= $totalCost;
            $user->save();
            
            // Créer la promotion
            $promotion = Promotion::create([
                'user_id' => $user->id,
                'produit_id' => $produit->id,
                'total_clicks' => $request->total_clicks,
                'remaining_clicks' => $request->total_clicks,
                'total_cost' => $totalCost,
                'status' => 'active',
                'started_at' => now()
            ]);
            
            // Marquer le produit comme promu
            $produit->update([
                'is_promoted' => true
            ]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Promotion créée',
                'promotion' => $promotion,
                'remaining_tokens' => $user->jetons
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur: ' . $e->getMessage()], 500);
        }
    }
    
    // Arrêter une promotion
    public function stop($promotionId)
    {
        $promotion = Promotion::findOrFail($promotionId);
        $user = auth()->user();
        
        if ($promotion->user_id !== $user->id) {
            return response()->json(['message' => 'Accès non autorisé'], 403);
        }
        
        if ($promotion->status !== 'active') {
            return response()->json(['message' => 'Promotion déjà terminée'], 400);
        }
        
        DB::beginTransaction();
        
        try {
            // Calculer le remboursement (clics restants * 0.2)
            $refundAmount = $promotion->remaining_clicks * self::COST_PER_CLICK;
            
            // Rembourser
            $user->jetons += $refundAmount;
            $user->save();
            
            // Mettre à jour la promotion
            $promotion->update([
                'status' => 'stopped',
                'ended_at' => now()
            ]);
            
            // Désactiver la promotion sur le produit
            $promotion->produit->update(['is_promoted' => false]);
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Promotion arrêtée',
                'refunded_tokens' => $refundAmount,
                'remaining_tokens' => $user->jetons
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Erreur: ' . $e->getMessage()], 500);
        }
    }
    
    // Récupérer la promotion active
    public function getActive($productId)
    {
        $produit = Produit::findOrFail($productId);
        $user = auth()->user();
        
        if ($produit->user_id !== $user->id) {
            return response()->json(['message' => 'Accès non autorisé'], 403);
        }
        
        $promotion = Promotion::where('produit_id', $productId)
            ->where('status', 'active')
            ->first();
            
        return response()->json([
            'success' => true,
            'promotion' => $promotion
        ]);
    }
}