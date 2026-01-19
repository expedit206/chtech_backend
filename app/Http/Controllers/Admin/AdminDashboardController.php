<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    /**
     * Récupère les statistiques globales pour le tableau de bord administrateur
     */
    public function index()
    {
        $stats = [
            'users' => [
                'total' => \App\Models\User::count(),
                'premium' => \App\Models\User::where('premium', true)->count(),
                'today' => \App\Models\User::whereDate('created_at', now()->today())->count(),
            ],
            'marketplace' => [
                'products_total' => \App\Models\Produit::count(),
                'services_total' => \App\Models\Service::count(),
                'pending_reventes' => \App\Models\Revente::where('statut', 'en_attente')->count(),
            ],
            'finance' => [
                'total_solde' => \App\Models\User::sum('solde'),
                'total_jetons' => \App\Models\User::sum('jetons'),
            ]
        ];
        return response()->json($stats);
    }
}
