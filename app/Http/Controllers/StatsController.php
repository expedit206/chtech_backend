<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StatsController extends Controller
{
    /**
     * Récupère les statistiques globales de l'utilisateur (parrainages, revenus, connexion)
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        // Calcul du nombre de parrainages actifs (filleuls ayant des produits)
        $parrainages = User::where('parrain_id', $user->id)
            ->where(function($q) {
                $q->whereHas('produits');
            })
            ->count();

        // Les revenus basés sur les jetons ont été supprimés.
        $revenus = 0;

        // Dernière connexion
        $last_login = $user->last_login ?? 'Non disponible';

        return response()->json([
            'parrainages' => $parrainages,
            'revenus' => $revenus,
            'last_login' => $last_login,
        ]);
    }
}