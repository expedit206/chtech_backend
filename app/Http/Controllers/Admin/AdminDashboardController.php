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
        // Statistiques globales simplifiées
        $stats = [
            'users' => [
                'total' => \App\Models\User::count(),
                'vendeurs' => \App\Models\User::where('role', 'vendeur')->count(),
                'today' => \App\Models\User::whereDate('created_at', now()->today())->count(),
            ],
            'marketplace' => [
                'products_total' => \App\Models\Produit::count(),
            ],
        ];

        // Données pour les graphiques (6 derniers mois)
        $months = collect();
        for ($i = 5; $i >= 0; $i--) {
            $months->push(now()->subMonths($i)->format('Y-m'));
        }

        $userRegistrations = \App\Models\User::selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, count(*) as count")
            ->where('created_at', '>=', now()->subMonths(6))
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->pluck('count', 'month');

        $productAdditions = \App\Models\Produit::selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, count(*) as count")
            ->where('created_at', '>=', now()->subMonths(6))
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->pluck('count', 'month');

        $stats['charts'] = [
            'labels' => $months->map(function($m) {
                return \Carbon\Carbon::parse($m)->translatedFormat('M Y');
            }),
            'users' => $months->map(fn($m) => $userRegistrations->get($m, 0)),
            'products' => $months->map(fn($m) => $productAdditions->get($m, 0)),
        ];

        return response()->json($stats);
    }
}
