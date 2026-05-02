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
     * L'Admin crée une commande depuis le résumé du chat (supporte multi-produits).
     * 
     * Payload: {
     *   user_id: UUID,
     *   items: [{ product_id: UUID, quantity: int, agreed_price?: float }]
     * }
     * Optimisation : une seule requête pour charger TOUS les produits (whereIn).
     */
    public function createFromAdmin(Request $request)
    {
        /** @var \App\Models\User $admin */
        $admin = Auth::user();
        if (!$admin->isAdmin()) {
            return response()->json(['message' => 'Accès réservé aux administrateurs'], 403);
        }

        $request->validate([
            'user_id'              => 'required|exists:users,id',
            'items'                => 'required|array|min:1|max:50',
            'items.*.product_id'   => 'required|exists:produits,id',
            'items.*.quantity'     => 'required|integer|min:1',
            'items.*.agreed_price' => 'nullable|numeric|min:0',
            'delivery_address'     => 'nullable|string|max:500',
            'contact_phone'        => 'nullable|string|max:50',
        ]);

        return DB::transaction(function () use ($request) {

            // --- 1. Charger tous les produits en UNE seule requête (performance) ---
            $productIds  = collect($request->items)->pluck('product_id')->unique()->values();
            $productsMap = Produit::whereIn('id', $productIds)
                ->lockForUpdate()              // row-level lock pour éviter race condition sur le stock
                ->get()
                ->keyBy('id');

            // --- 2. Vérifier le stock pour tous les articles AVANT de toucher à la DB ---
            $orderItems  = [];
            $totalAmount = 0;

            foreach ($request->items as $itemData) {
                /** @var \App\Models\Produit $product */
                $product = $productsMap->get($itemData['product_id']);

                if (!$product) {
                    return response()->json(['message' => "Produit {$itemData['product_id']} introuvable"], 404);
                }

                if ($product->quantite < $itemData['quantity']) {
                    return response()->json([
                        'message' => "Stock insuffisant pour : {$product->nom} (disponible : {$product->quantite})"
                    ], 400);
                }

                $unitPrice    = $itemData['agreed_price'] ?? $product->prix;
                $totalAmount += $unitPrice * $itemData['quantity'];

                $orderItems[] = [
                    'product' => $product,
                    'quantity'   => $itemData['quantity'],
                    'unit_price' => $unitPrice,
                ];
            }

            // --- 3. Créer la commande ---
            $order = Order::create([
                'user_id'          => $request->user_id,
                'total_amount'     => $totalAmount,
                'status'           => 'pending',
                'payment_status'   => 'paid',
                'delivery_address' => $request->delivery_address ?? null,
                'contact_phone'    => $request->contact_phone   ?? null,
            ]);

            // --- 4. Insérer tous les order items en BATCH (1 seule requête) + décrémenter stock ---
            $now           = now();
            $itemsToInsert = [];

            foreach ($orderItems as $line) {
                /** @var \App\Models\Produit $product */
                $product = $line['product'];

                $itemsToInsert[] = [
                    'id'          => (string) \Illuminate\Support\Str::uuid(),
                    'order_id'    => $order->id,
                    'produit_id'  => $product->id,
                    'supplier_id' => $product->user_id,
                    'quantity'    => $line['quantity'],
                    'price'       => $line['unit_price'],
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];

                // Décrémenter le stock directement via UPDATE (pas de select N+1)
                Produit::where('id', $product->id)
                    ->decrement('quantite', $line['quantity']);
            }

            // Batch insert : 1 seule requête INSERT pour N articles
            OrderItem::insert($itemsToInsert);

            // --- 5. Notifier l'acheteur ---
            $buyer = User::find($request->user_id);
            if ($buyer) {
                $buyer->notify(\App\Notifications\OrderNotification::make($order, 'créée'));
            }

            return response()->json([
                'success' => true,
                'message' => 'Commande multi-articles générée avec succès.',
                'order'   => $order->load('items.produit'),
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

    /**
     * Liste toutes les commandes pour l'administration
     */
    public function adminIndex()
    {
        $user = Auth::user();
        if (!$user->isAdmin()) {
            return response()->json(['message' => 'Accès réservé aux administrateurs'], 403);
        }

        $orders = Order::with(['user', 'items.produit', 'items.supplier'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['success' => true, 'data' => $orders]);
    }
}

