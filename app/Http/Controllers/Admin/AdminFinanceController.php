<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\JetonTransaction;
use App\Models\User;
use App\Models\Abonnement;

class AdminFinanceController extends Controller
{
    /**
     * Get financial statistics and transaction history
     */
    public function index()
    {
        return response()->json([
            'total_volume' => JetonTransaction::where('statut', 'confirmé')->sum('montant_total'),
            'total_commission' => JetonTransaction::where('statut', 'confirmé')->sum('commission_plateforme'),
            'platform_sales' => JetonTransaction::where('type', 'platform')
                                               ->where('statut', 'confirmé')
                                               ->sum('montant_total'),
            'marketplace_volume' => JetonTransaction::where('type', 'marketplace')
                                                  ->where('statut', 'confirmé')
                                                  ->sum('montant_total'),
        ]);
    }

    /**
     * Get paginated transactions
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
}
