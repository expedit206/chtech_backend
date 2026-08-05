<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Commission;
use App\Models\PromoCode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminPromoCodeController extends Controller
{
    /**
     * Obtenir la liste de tous les codes promo avec statistiques
     */
    public function index(Request $request)
    {
        $query = PromoCode::with(['owner:id,nom,email,telephone'])
            ->withCount('usages');

        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('owner', function ($uq) use ($search) {
                      $uq->where('nom', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->has('is_active') && $request->is_active !== null) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $promoCodes = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 15));

        // Ajouter le total des réductions et commissions générées pour chaque code
        $promoCodes->getCollection()->transform(function ($code) {
            $code->total_discounts_given = (float) $code->usages()->sum('discount_amount');
            $code->total_commissions_generated = (float) $code->commissions()->sum('amount');
            return $code;
        });

        return response()->json([
            'status' => 'success',
            'data' => $promoCodes
        ]);
    }

    /**
     * Créer un nouveau code promo et l'attribuer optionnellement à un ambassadeur
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:promo_codes,code',
            'description' => 'nullable|string',
            'user_id' => 'nullable|exists:users,id', // Ambassadeur / Bénéficiaire
            'discount_type' => 'required|in:percentage,fixed_amount',
            'discount_value' => 'required|numeric|min:0.01',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'commission_type' => 'required|in:percentage,fixed_amount',
            'commission_value' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'usage_per_user_limit' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:starts_at',
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));
        $validated['min_order_amount'] = $validated['min_order_amount'] ?? 0;
        $validated['commission_value'] = $validated['commission_value'] ?? 0;
        $validated['usage_per_user_limit'] = $validated['usage_per_user_limit'] ?? 1;
        $validated['is_active'] = $validated['is_active'] ?? true;

        $promoCode = PromoCode::create($validated);
        $promoCode->load('owner:id,nom,email');

        return response()->json([
            'status' => 'success',
            'message' => 'Code promo créé avec succès.',
            'data' => $promoCode
        ], 201);
    }

    /**
     * Afficher les détails d'un code promo
     */
    public function show($id)
    {
        $promoCode = PromoCode::with(['owner:id,nom,email,telephone', 'usages.user:id,nom,email', 'usages.order', 'commissions'])
            ->findOrFail($id);

        $stats = [
            'total_usages' => $promoCode->usages()->count(),
            'total_discount_amount' => (float) $promoCode->usages()->sum('discount_amount'),
            'total_commission_amount' => (float) $promoCode->commissions()->sum('amount'),
            'pending_commission_amount' => (float) $promoCode->commissions()->where('status', 'pending')->sum('amount'),
            'credited_commission_amount' => (float) $promoCode->commissions()->where('status', 'credited')->sum('amount'),
        ];

        return response()->json([
            'status' => 'success',
            'data' => [
                'promo_code' => $promoCode,
                'stats' => $stats
            ]
        ]);
    }

    /**
     * Mettre à jour un code promo
     */
    public function update(Request $request, $id)
    {
        $promoCode = PromoCode::findOrFail($id);

        $validated = $request->validate([
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('promo_codes', 'code')->ignore($promoCode->id)],
            'description' => 'nullable|string',
            'user_id' => 'nullable|exists:users,id',
            'discount_type' => 'sometimes|required|in:percentage,fixed_amount',
            'discount_value' => 'sometimes|required|numeric|min:0.01',
            'max_discount_amount' => 'nullable|numeric|min:0',
            'min_order_amount' => 'nullable|numeric|min:0',
            'commission_type' => 'sometimes|required|in:percentage,fixed_amount',
            'commission_value' => 'nullable|numeric|min:0',
            'usage_limit' => 'nullable|integer|min:1',
            'usage_per_user_limit' => 'nullable|integer|min:1',
            'is_active' => 'boolean',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date',
        ]);

        if (isset($validated['code'])) {
            $validated['code'] = strtoupper(trim($validated['code']));
        }

        $promoCode->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Code promo mis à jour avec succès.',
            'data' => $promoCode->fresh(['owner:id,nom,email'])
        ]);
    }

    /**
     * Basculer le statut d'activation d'un code promo
     */
    public function toggleStatus($id)
    {
        $promoCode = PromoCode::findOrFail($id);
        $promoCode->is_active = !$promoCode->is_active;
        $promoCode->save();

        return response()->json([
            'status' => 'success',
            'message' => $promoCode->is_active ? 'Code promo activé.' : 'Code promo désactivé.',
            'data' => $promoCode
        ]);
    }

    /**
     * Supprimer un code promo
     */
    public function destroy($id)
    {
        $promoCode = PromoCode::findOrFail($id);
        $promoCode->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Code promo supprimé.'
        ]);
    }

    /**
     * Obtenir toutes les commissions du système (Reporting Admin)
     */
    public function commissions(Request $request)
    {
        $query = Commission::with(['user:id,nom,email,telephone', 'promoCode:id,code', 'order:id,total_amount,status']);

        if ($request->has('status') && !empty($request->status)) {
            $query->where('status', $request->status);
        }

        if ($request->has('user_id') && !empty($request->user_id)) {
            $query->where('user_id', $request->user_id);
        }

        $commissions = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 20));

        $stats = [
            'total_commissions' => (float) Commission::sum('amount'),
            'credited_commissions' => (float) Commission::where('status', 'credited')->sum('amount'),
            'pending_commissions' => (float) Commission::where('status', 'pending')->sum('amount'),
        ];

        return response()->json([
            'status' => 'success',
            'data' => $commissions,
            'stats' => $stats
        ]);
    }

    /**
     * Mettre à jour le statut d'une commission (ex: valider/créditer manuellement)
     */
    public function updateCommissionStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,credited,cancelled',
            'notes' => 'nullable|string'
        ]);

        $commission = Commission::findOrFail($id);
        $commission->status = $request->status;
        if ($request->has('notes')) {
            $commission->notes = $request->notes;
        }
        $commission->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Statut de la commission mis à jour.',
            'data' => $commission
        ]);
    }
}
