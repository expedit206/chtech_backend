<?php

namespace App\Http\Controllers;

use App\Models\PromoCode;
use Illuminate\Http\Request;

class PromoCodeController extends Controller
{
    /**
     * Valider un code promo lors du panier ou du checkout
     */
    public function validateCode(Request $request)
    {
        $request->validate([
            'code' => 'required|string',
            'order_amount' => 'required|numeric|min:0',
        ]);

        $codeStr = strtoupper(trim($request->code));
        $orderAmount = (float) $request->order_amount;
        $user = $request->user(); // Peut être nul si invité, ou utilisateur authentifié

        $promoCode = PromoCode::where('code', $codeStr)->first();

        if (!$promoCode) {
            return response()->json([
                'status' => 'error',
                'valid' => false,
                'message' => 'Code promo inexistant ou invalide.'
            ], 404);
        }

        $check = $promoCode->isValidFor($user, $orderAmount);

        if (!$check['valid']) {
            return response()->json([
                'status' => 'error',
                'valid' => false,
                'message' => $check['message']
            ], 422);
        }

        $discountAmount = $promoCode->calculateDiscount($orderAmount);
        $finalAmount = max(0, $orderAmount - $discountAmount);

        return response()->json([
            'status' => 'success',
            'valid' => true,
            'message' => 'Code promo appliqué avec succès !',
            'data' => [
                'promo_code_id' => $promoCode->id,
                'code' => $promoCode->code,
                'description' => $promoCode->description,
                'discount_type' => $promoCode->discount_type,
                'discount_value' => $promoCode->discount_value,
                'discount_amount' => $discountAmount,
                'original_amount' => $orderAmount,
                'final_amount' => $finalAmount,
            ]
        ]);
    }
}
