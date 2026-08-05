<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromoCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'description',
        'user_id',
        'discount_type',
        'discount_value',
        'max_discount_amount',
        'min_order_amount',
        'commission_type',
        'commission_value',
        'usage_limit',
        'usage_per_user_limit',
        'used_count',
        'is_active',
        'starts_at',
        'expires_at',
    ];

    protected $casts = [
        'discount_value' => 'float',
        'max_discount_amount' => 'float',
        'min_order_amount' => 'float',
        'commission_value' => 'float',
        'usage_limit' => 'integer',
        'usage_per_user_limit' => 'integer',
        'used_count' => 'integer',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Propriétaire du code promo (Ambassadeur)
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Historique des utilisations
     */
    public function usages(): HasMany
    {
        return $this->hasMany(PromoCodeUsage::class);
    }

    /**
     * Commissions générées
     */
    public function commissions(): HasMany
    {
        return $this->hasMany(Commission::class);
    }

    /**
     * Vérifier la validité du code promo
     */
    public function isValidFor(?User $user, float $orderAmount): array
    {
        if (!$this->is_active) {
            return ['valid' => false, 'message' => 'Ce code promo est inactif.'];
        }

        $now = now();
        if ($this->starts_at && $now->lt($this->starts_at)) {
            return ['valid' => false, 'message' => 'Ce code promo n\'est pas encore valide.'];
        }

        if ($this->expires_at && $now->gt($this->expires_at)) {
            return ['valid' => false, 'message' => 'Ce code promo a expiré.'];
        }

        if ($this->usage_limit !== null && $this->used_count >= $this->usage_limit) {
            return ['valid' => false, 'message' => 'Le quota d\'utilisation de ce code promo est atteint.'];
        }

        if ($orderAmount < $this->min_order_amount) {
            return [
                'valid' => false, 
                'message' => "Le montant minimum de commande pour ce code est de {$this->min_order_amount} FCFA."
            ];
        }

        if ($user) {
            // Anti auto-parrainage : l'ambassadeur ne peut pas utiliser son propre code
            if ($this->user_id && $this->user_id === $user->id) {
                return ['valid' => false, 'message' => 'Vous ne pouvez pas utiliser votre propre code promo.'];
            }

            if ($this->usage_per_user_limit > 0) {
                $userUsageCount = $this->usages()->where('user_id', $user->id)->count();
                if ($userUsageCount >= $this->usage_per_user_limit) {
                    return ['valid' => false, 'message' => 'Vous avez déjà atteint la limite d\'utilisation pour ce code promo.'];
                }
            }
        }

        return ['valid' => true, 'message' => 'Code promo valide.'];
    }

    /**
     * Calculer la réduction pour un sous-total donné
     */
    public function calculateDiscount(float $subtotal): float
    {
        if ($this->discount_type === 'percentage') {
            $discount = $subtotal * ($this->discount_value / 100);
            if ($this->max_discount_amount && $discount > $this->max_discount_amount) {
                $discount = $this->max_discount_amount;
            }
            return round($discount, 2);
        }

        return min(round($this->discount_value, 2), $subtotal);
    }

    /**
     * Calculer la commission pour un sous-total/total donné
     */
    public function calculateCommission(float $orderTotal): float
    {
        if (!$this->user_id || $this->commission_value <= 0) {
            return 0.00;
        }

        if ($this->commission_type === 'percentage') {
            return round($orderTotal * ($this->commission_value / 100), 2);
        }

        return round($this->commission_value, 2);
    }
}
