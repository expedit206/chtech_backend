<?php

namespace App\Http\Controllers;

use App\Models\Commission;
use App\Models\PromoCode;
use Illuminate\Http\Request;

class UserCommissionController extends Controller
{
    /**
     * Obtenir la liste des codes promo attribués à l'utilisateur connecté
     */
    public function myPromoCodes(Request $request)
    {
        $user = $request->user();

        $promoCodes = PromoCode::where('user_id', $user->id)
            ->withCount('usages')
            ->orderBy('created_at', 'desc')
            ->get();

        $promoCodes->transform(function ($code) {
            $code->total_discounts_given = (float) $code->usages()->sum('discount_amount');
            $code->total_commissions_earned = (float) $code->commissions()->sum('amount');
            return $code;
        });

        return response()->json([
            'status' => 'success',
            'data' => $promoCodes
        ]);
    }

    /**
     * Obtenir le relevé des commissions et les statistiques de l'ambassadeur
     */
    public function myCommissions(Request $request)
    {
        $user = $request->user();

        $query = Commission::where('user_id', $user->id)
            ->with(['promoCode:id,code,description', 'order:id,total_amount,created_at']);

        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }

        $commissions = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 15));

        $stats = [
            'total_earned' => (float) Commission::where('user_id', $user->id)->sum('amount'),
            'credited_balance' => (float) Commission::where('user_id', $user->id)->where('status', 'credited')->sum('amount'),
            'pending_balance' => (float) Commission::where('user_id', $user->id)->where('status', 'pending')->sum('amount'),
            'total_sales_generated' => (float) PromoCode::where('user_id', $user->id)->has('usages')->with('usages.order')->get()->sum(function ($code) {
                return $code->usages->sum(function ($usage) {
                    return $usage->order ? $usage->order->total_amount : 0;
                });
            })
        ];

        return response()->json([
            'status' => 'success',
            'data' => $commissions,
            'stats' => $stats
        ]);
    }
}
