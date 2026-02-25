<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Produit;
use App\Models\PromotionEvent;
use Illuminate\Http\Request;

class AdminProductPromotionController extends Controller
{
    /**
     * Liste tous les produits avec leur statut de promotion
     */
    public function index()
    {
        $produits = Produit::with('category', 'user')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $produits]);
    }

    /**
     * Activer/Désactiver la promotion d'un produit (Section Promo Accueil)
     */
    public function togglePromotion($id)
    {
        $produit = Produit::findOrFail($id);
        $produit->is_promoted = !$produit->is_promoted;
        $produit->save();

        return response()->json([
            'success' => true,
            'message' => $produit->is_promoted ? "Produit ajouté à la section promotion" : "Produit retiré de la section promotion",
            'is_promoted' => $produit->is_promoted
        ]);
    }

    public function getActiveEvent()
    {
        $event = PromotionEvent::where('is_active', true)->orderBy('created_at', 'desc')->first();
        if (!$event) {
            // Créer un event par défaut s'il n'y en a pas
            $event = PromotionEvent::create([
                'title' => 'Spéciale Promo !! | Stock Limité',
                'end_date' => now()->addDays(14),
                'is_active' => true
            ]);
        }
        return response()->json(['success' => true, 'data' => $event]);
    }

    public function updateEvent(Request $request)
    {
        $request->validate([
            'title' => 'required|string',
            'end_date' => 'required|date',
        ]);

        $event = PromotionEvent::where('is_active', true)->orderBy('created_at', 'desc')->first();
        if (!$event) {
            $event = new PromotionEvent();
        }

        $event->title = $request->title;
        $event->end_date = $request->end_date;
        $event->is_active = true;
        $event->save();

        return response()->json(['success' => true, 'message' => 'Événement mis à jour', 'data' => $event]);
    }
}
