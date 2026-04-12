<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;

class FactureController extends Controller
{
    /**
     * Génère et télécharge une facture PDF pour une commande donnée.
     */
    public function download(string $id)
    {
        $order = Order::with(['items.produit', 'user'])->findOrFail($id);
        $user = Auth::user();

        // Sécurité : Seul l'acheteur ou un admin peut voir la facture
        if ($order->user_id !== $user->id && $user->role !== 'admin') {
            return response()->json(['message' => 'Accès non autorisé'], 403);
        }

        $data = [
            'order' => $order,
            'user' => $order->user,
            'items' => $order->items,
            'date' => now()->format('d/m/Y'),
            'logo' => public_path('logo-sasaye.jpeg') // Utiliser le logo local si possible
        ];

        $pdf = Pdf::loadView('pdf.facture', $data);

        return $pdf->download('facture-SASAYEE-' . $order->id . '.pdf');
    }
}
