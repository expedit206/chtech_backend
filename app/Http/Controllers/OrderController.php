<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Produit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Notifications\OrderNotification;

class OrderController extends Controller
{
    /**
     * L'Admin crée une commande directement depuis le resumé du chat
     */
    public function createFromAdmin(Request $request)
    {
        /** @var \App\Models\User $admin */
        $admin = Auth::user();
        if (!$admin->isAdmin()) {
            return response()->json(['message' => 'Accès réservé aux administrateurs'], 403);
        }

        $request->validate([
            'user_id' => 'required|exists:users,id',
            'product_id' => 'required|exists:produits,id',
            'quantity' => 'required|integer|min:1',
            'agreed_price' => 'numeric|min:0', // Optionnel si on négocie en chat
        ]);

        return DB::transaction(function () use ($request) {
            $product = Produit::findOrFail($request->product_id);

            if ($product->quantite < $request->quantity) {
                 return response()->json(['message' => "Stock insuffisant pour le produit: {$product->nom}"], 400);
            }

            $unitPrice = $request->has('agreed_price') ? $request->agreed_price : $product->prix;
            $totalAmount = $unitPrice * $request->quantity;

            // Création de l'entité commande
            $order = Order::create([
                'user_id' => $request->user_id,
                'total_amount' => $totalAmount,
                'status' => 'pending', // par défaut, le statut commence
                'payment_status' => 'paid', // le paiement s'est fait off-platform (mobile money)
                // Ces champs sont maintenant nullable
                'delivery_address' => null, 
                'contact_phone' => null,
            ]);

            // Ajout de l'item à la commande
            OrderItem::create([
                'order_id' => $order->id,
                'produit_id' => $product->id,
                'supplier_id' => $product->user_id,
                'quantity' => $request->quantity,
                'price' => $unitPrice,
            ]);

            // Décrémenter le stock
            $product->decrement('quantite', $request->quantity);

            return response()->json([
                'success' => true,
                'message' => 'Commande générée avec succès pour le client.',
                'order' => $order->load('items.produit')
            ], 201);
        });
    }
    /**
     * Passer une commande (Simulation Escrow incluse)
     */
    public function store(Request $request)
    {
        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:produits,id',
            'items.*.quantity' => 'required|integer|min:1',
            'delivery_address' => 'required|string',
            'contact_phone' => 'required|string',
        ]);

        return DB::transaction(function () use ($request) {
            $totalAmount = 0;
            $orderItems = [];

            foreach ($request->items as $itemData) {
                $product = Produit::findOrFail($itemData['product_id']);

                if ($product->quantite < $itemData['quantity']) {
                    throw new \Exception("Stock insuffisant pour le produit: {$product->nom}");
                }

                $itemPrice = $product->prix * $itemData['quantity'];
                $totalAmount += $itemPrice;

                $orderItems[] = [
                    'produit_id' => $product->id,
                    'supplier_id' => $product->user_id,
                    'quantity' => $itemData['quantity'],
                    'price' => $product->prix,
                ];

                // Décrémenter le stock
                $product->decrement('quantite', $itemData['quantity']);
            }

            $order = Order::create([
                'user_id' => Auth::id(),
                'total_amount' => $totalAmount,
                'status' => 'pending',
                'payment_status' => 'escrow', // Simulation : l'argent est bloqué
                'delivery_address' => $request->delivery_address,
                'contact_phone' => $request->contact_phone,
            ]);

            foreach ($orderItems as $item) {
                $item['order_id'] = $order->id;
                OrderItem::create($item);
                
                // NOTIFIER LE VENDEUR
                $seller = User::find($item['supplier_id']);
                if ($seller) {
                    $seller->notify(OrderNotification::forSeller($order));
                }
            }

            // NOTIFIER L'ACHETEUR
            $buyer = Auth::user();
            $buyer->notify(OrderNotification::make($order, 'reçue (en attente)'));

            return response()->json([
                'success' => true,
                'message' => 'Commande passée avec succès. Paiement sécurisé en attente (Escrow).',
                'order' => $order->load('items.produit')
            ], 201);
        });
    }

    /**
     * Liste des commandes de l'utilisateur connecté
     */
    public function index()
    {
        $orders = Order::where('user_id', Auth::id())
            ->with('items.produit')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $orders]);
    }

    /**
     * Liste des commandes reçues par un vendeur
     */
    public function sellerOrders()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $orderItems = OrderItem::where('supplier_id', $user->id)
            ->with(['order.user', 'produit'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $orderItems]);
    }

    /**
     * Mettre à jour le statut (Simulation Livraison / Escrow Release)
     */
    public function updateStatus(Request $request, $orderId)
    {
        $order = Order::findOrFail($orderId);
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Seul l'administrateur peut changer le statut des commandes dans ce flux sécurisé
        if (!$user->isAdmin()) {
            return response()->json(['message' => 'Accès réservé aux administrateurs'], 403);
        }

        $request->validate([
            'status' => 'required|in:pending,shipped,delivered,cancelled',
        ]);

        $order->status = $request->status;

        // Simulation Escrow : Si livré, le paiement passe de 'escrow' à 'paid'
        if ($request->status === 'delivered') {
            $order->payment_status = 'paid';
        }

        $order->save();

        // NOTIFIER L'ACHETEUR DU CHANGEMENT DE STATUT
        $buyer = $order->user;
        if ($buyer) {
            $buyer->notify(OrderNotification::make($order, $request->status));
        }

        return response()->json([
            'success' => true,
            'message' => "Statut mis à jour : {$request->status}. " .
                ($order->payment_status === 'paid' ? "Paiement libéré au vendeur." : "")
        ]);
    }

    /**
     * Statistiques de vente pour le tableau de bord vendeur
     */
    public function sellerStats()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (!$user->isVendeur() && !$user->isAdmin()) {
            return response()->json(['message' => 'Accès réservé aux vendeurs'], 403);
        }

        // Toutes les commandes contenant les produits du vendeur
        $orderItems = OrderItem::where('supplier_id', $user->id)
            ->with('order')
            ->get();

        $totalOrders = $orderItems->count();
        $pendingOrders = $orderItems->filter(fn($item) => in_array($item->order?->status, ['pending', 'shipped']))->count();
        $deliveredOrders = $orderItems->filter(fn($item) => $item->order?->status === 'delivered')->count();
        $totalRevenue = $orderItems
            ->filter(fn($item) => in_array($item->order?->status, ['delivered']) || $item->order?->payment_status === 'paid')
            ->sum(fn($item) => $item->price * $item->quantity);

        // Produits actifs de ce vendeur (stock > 0)
        $activeProducts = \App\Models\Produit::where('user_id', $user->id)
            ->where('quantite', '>', 0)
            ->count();

        // Vues totales des produits (depuis la table product_views)
        $produitIds = \App\Models\Produit::where('user_id', $user->id)->pluck('id');
        $totalViews = \Illuminate\Support\Facades\DB::table('product_views')
            ->whereIn('produit_id', $produitIds)
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_orders' => $totalOrders,
                'pending_orders' => $pendingOrders,
                'delivered_orders' => $deliveredOrders,
                'total_revenue' => $totalRevenue,
                'active_products' => $activeProducts,
                'total_views' => $totalViews,
            ]
        ]);
    }

    /**
     * Données financières détaillées pour le vendeur (graphiques)
     */
    public function sellerFinance()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (!$user->isVendeur() && !$user->isAdmin()) {
            return response()->json(['message' => 'Accès réservé aux vendeurs'], 403);
        }

        // --- Revenus mensuels sur 12 mois ---
        $monthlyRevenue = [];
        $monthlyLabels = [];
        for ($i = 11; $i >= 0; $i--) {
            $date = now()->subMonths($i);
            $monthlyLabels[] = $date->format('m/Y');
            $monthlyRevenue[] = OrderItem::where('supplier_id', $user->id)
                ->whereHas('order', fn($q) => $q->where('status', 'delivered')
                    ->whereYear('created_at', $date->year)
                    ->whereMonth('created_at', $date->month))
                ->get()
                ->sum(fn($item) => $item->price * $item->quantity);
        }

        // --- Répartition des commandes par statut ---
        $allItems = OrderItem::where('supplier_id', $user->id)->with('order')->get();
        $statusBreakdown = [
            'delivered' => $allItems->filter(fn($i) => $i->order?->status === 'delivered')->count(),
            'pending'   => $allItems->filter(fn($i) => $i->order?->status === 'pending')->count(),
            'shipped'   => $allItems->filter(fn($i) => $i->order?->status === 'shipped')->count(),
            'cancelled' => $allItems->filter(fn($i) => $i->order?->status === 'cancelled')->count(),
        ];

        // --- Top 5 produits par revenus générés ---
        $topProducts = OrderItem::where('supplier_id', $user->id)
            ->whereHas('order', fn($q) => $q->whereIn('status', ['delivered', 'pending', 'shipped']))
            ->with('produit:id,nom,photos')
            ->get()
            ->groupBy('produit_id')
            ->map(function ($items) {
                $first = $items->first();
                return [
                    'id'      => $first->produit_id,
                    'nom'     => $first->produit?->nom ?? 'Produit supprimé',
                    'photo'   => $first->produit?->photos[0] ?? null,
                    'revenue' => $items->sum(fn($i) => $i->price * $i->quantity),
                    'orders'  => $items->count(),
                ];
            })
            ->sortByDesc('revenue')
            ->values()
            ->take(5);

        // --- KPIs globaux ---
        $totalRevenue = $allItems
            ->filter(fn($i) => $i->order?->status === 'delivered')
            ->sum(fn($i) => $i->price * $i->quantity);

        $pendingRevenue = $allItems
            ->filter(fn($i) => in_array($i->order?->status, ['pending', 'shipped']))
            ->sum(fn($i) => $i->price * $i->quantity);

        $avgOrderValue = $allItems->count() > 0
            ? $allItems->avg(fn($i) => $i->price * $i->quantity)
            : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'chart' => [
                    'labels'          => $monthlyLabels,
                    'monthly_revenue' => $monthlyRevenue,
                ],
                'status_breakdown' => $statusBreakdown,
                'top_products'     => $topProducts,
                'kpis' => [
                    'total_revenue'   => round($totalRevenue),
                    'pending_revenue' => round($pendingRevenue),
                    'avg_order_value' => round($avgOrderValue),
                    'total_orders'    => $allItems->count(),
                ],
            ]
        ]);
    }
}

