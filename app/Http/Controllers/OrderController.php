<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Produit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
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
            }

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
     * Liste des commandes reçues par un fournisseur
     */
    public function supplierOrders()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        if (!$user->isFournisseur() && !$user->isAdmin()) {
            return response()->json(['message' => 'Accès réservé aux fournisseurs'], 403);
        }

        // Récupérer les items de commande destinés à ce fournisseur
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

        // Vérifier si le fournisseur possède au moins un item dans cette commande
        $hasItem = OrderItem::where('order_id', $orderId)
            ->where('supplier_id', $user->id)
            ->exists();

        if (!$hasItem && !$user->isAdmin()) {
            return response()->json(['message' => 'Non autorisé'], 403);
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

        return response()->json([
            'success' => true,
            'message' => "Statut mis à jour : {$request->status}. " .
                ($order->payment_status === 'paid' ? "Paiement libéré au fournisseur." : "")
        ]);
    }
}
