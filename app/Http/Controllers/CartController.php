<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Produit;

class CartController extends Controller
{
    // Récupérer le panier de l'utilisateur
    public function getCart(Request $request)
    {
        $user = Auth::user();
        $cart = $user->cart ?? [];
        return response()->json(['success' => true, 'data' => $cart]);
    }

    // Ajouter un produit au panier
    public function addToCart(Request $request)
    {
        $user = Auth::user();
        $productId = $request->input('product_id');
        $quantity = $request->input('quantity', 1);
        $product = Produit::find($productId);
        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Produit introuvable'], 404);
        }
        $cart = $user->cart ?? [];
        $found = false;
        foreach ($cart as &$item) {
            if ($item['id'] == $productId) {
                $item['quantity'] += $quantity;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $cart[] = [
                'id' => $product->id,
                'name' => $product->name,
                'price' => $product->price,
                'image' => $product->image,
                'quantity' => $quantity
            ];
        }
        $user->cart = $cart;
        $user->save();
        return response()->json(['success' => true, 'data' => $cart]);
    }

    // Supprimer un produit du panier
    public function removeFromCart(Request $request)
    {
        $user = Auth::user();
        $productId = $request->input('product_id');
        $cart = $user->cart ?? [];
        $cart = array_filter($cart, function($item) use ($productId) {
            return $item['id'] != $productId;
        });
        $user->cart = array_values($cart);
        $user->save();
        return response()->json(['success' => true, 'data' => $user->cart]);
    }

    // Mettre à jour la quantité
    public function updateQuantity(Request $request)
    {
        $user = Auth::user();
        $productId = $request->input('product_id');
        $quantity = $request->input('quantity');
        $cart = $user->cart ?? [];
        foreach ($cart as &$item) {
            if ($item['id'] == $productId) {
                $item['quantity'] = $quantity;
                break;
            }
        }
        $user->cart = $cart;
        $user->save();
        return response()->json(['success' => true, 'data' => $cart]);
    }

    // Vider le panier
    public function clearCart(Request $request)
    {
        $user = Auth::user();
        $user->cart = [];
        $user->save();
        return response()->json(['success' => true, 'data' => []]);
    }
}
