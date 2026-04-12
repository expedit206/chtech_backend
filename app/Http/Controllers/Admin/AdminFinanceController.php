<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Order;
use App\Models\OrderItem;

class AdminFinanceController extends Controller
{
    /**
     * Get financial statistics (Orders)
     */
    public function index()
    {
        // total_volume: somme des commandes livrées
        $totalVolume = Order::where('status', 'delivered')->sum('total_amount');
        
        return response()->json([
            'total_volume' => $totalVolume,
            'total_commission' => 0, // Placeholder
            'platform_sales' => 0,   // Placeholder
            'marketplace_volume' => $totalVolume,
        ]);
    }

    /**
     * Get paginated JetonTransactions
     */
    public function transactions(Request $request)
    {
        $query = JetonTransaction::with(['acheteur', 'vendeur'])
            ->orderBy('created_at', 'desc');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->whereHas('acheteur', function($sq) use ($search) {
                    $sq->where('nom', 'like', "%{$search}%");
                })->orWhereHas('vendeur', function($sq) use ($search) {
                    $sq->where('nom', 'like', "%{$search}%");
                })->orWhere('notchpay_reference', 'like', "%{$search}%");
            });
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('status')) {
            $query->where('statut', $request->status);
        }

        return $query->paginate(15);
    }

    /**
     * Platform-wide order-based financial stats with chart data
     */
    public function orderStats()
    {
        // --- Revenus et commandes mensuels sur 12 mois ---
        $monthlyLabels  = [];
        $monthlyRevenue = [];
        $monthlyOrders  = [];

        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $monthlyLabels[] = $date->format('m/Y');

            $revenue = OrderItem::whereHas('order', fn($q) =>
                $q->where('status', 'delivered')
                  ->whereYear('created_at', $date->year)
                  ->whereMonth('created_at', $date->month)
            )->get()->sum(fn($item) => $item->price * $item->quantity);

            $ordersCount = Order::whereYear('created_at', $date->year)
                ->whereMonth('created_at', $date->month)
                ->count();

            $monthlyRevenue[] = round($revenue);
            $monthlyOrders[]  = $ordersCount;
        }

        // --- Répartition globale par statut ---
        $statusCounts = Order::selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        // --- KPIs globaux ---
        $allItems = OrderItem::with('order')->get();

        $totalRevenue = $allItems
            ->filter(fn($i) => $i->order?->status === 'delivered')
            ->sum(fn($i) => $i->price * $i->quantity);

        $pendingRevenue = $allItems
            ->filter(fn($i) => in_array($i->order?->status, ['pending', 'shipped']))
            ->sum(fn($i) => $i->price * $i->quantity);

        $totalOrders    = Order::count();
        $deliveredCount = Order::where('status', 'delivered')->count();
        $avgOrderValue  = Order::avg('total_amount') ?? 0;

        // --- Top 5 vendeurs par revenus ---
        $topSellers = OrderItem::whereHas('order', fn($q) => $q->whereIn('status', ['delivered', 'pending', 'shipped']))
            ->with('supplier:id,nom,photo')
            ->get()
            ->groupBy('supplier_id')
            ->map(function ($items) {
                $first = $items->first();
                return [
                    'id'      => $first->supplier_id,
                    'nom'     => $first->supplier?->nom ?? 'Vendeur inconnu',
                    'photo'   => $first->supplier?->photo ?? null,
                    'revenue' => round($items->sum(fn($i) => $i->price * $i->quantity)),
                    'orders'  => $items->count(),
                ];
            })
            ->sortByDesc('revenue')
            ->values()
            ->take(5);

        // --- Top 5 produits les plus vendus ---
        $topProducts = OrderItem::whereHas('order', fn($q) => $q->whereIn('status', ['delivered', 'pending', 'shipped']))
            ->with('produit:id,nom,photos')
            ->get()
            ->groupBy('produit_id')
            ->map(function ($items) {
                $first = $items->first();
                return [
                    'id'      => $first->produit_id,
                    'nom'     => $first->produit?->nom ?? 'Produit supprimé',
                    'photo'   => $first->produit?->photos[0] ?? null,
                    'revenue' => round($items->sum(fn($i) => $i->price * $i->quantity)),
                    'orders'  => $items->count(),
                ];
            })
            ->sortByDesc('revenue')
            ->values()
            ->take(5);

        return response()->json([
            'success' => true,
            'data' => [
                'chart' => [
                    'labels'          => $monthlyLabels,
                    'monthly_revenue' => $monthlyRevenue,
                    'monthly_orders'  => $monthlyOrders,
                ],
                'status_breakdown' => $statusCounts,
                'top_sellers'      => $topSellers,
                'top_products'     => $topProducts,
                'kpis' => [
                    'total_revenue'    => round($totalRevenue),
                    'pending_revenue'  => round($pendingRevenue),
                    'total_orders'     => $totalOrders,
                    'delivered_orders' => $deliveredCount,
                    'avg_order_value'  => round($avgOrderValue),
                ],
            ]
        ]);
    }
}
