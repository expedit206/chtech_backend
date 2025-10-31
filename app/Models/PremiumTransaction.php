<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PremiumTransaction extends Model
{
    protected $table = 'premium_transactions';
    
    protected $fillable = [
        'user_id',
        'type_abonnement',
        'montant',
        'transaction_id_notchpay',
        'statut',
        'notchpay_metadata',
        'date_transaction'
    ];

    protected $casts = [
        'notchpay_metadata' => 'array',
        'date_transaction' => 'datetime',
        'montant' => 'decimal:2'
    ];

    protected $attributes = [
        'statut' => 'en_attente'
    ];

    /**
     * Relation avec l'utilisateur
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope pour les transactions en attente
     */
    public function scopeEnAttente($query)
    {
        return $query->where('statut', 'en_attente');
    }

    /**
     * Vérifier si la transaction est en attente
     */
    public function isEnAttente(): bool
    {
        return $this->statut === 'en_attente';
    }

    /**
     * Obtenir le libellé du statut
     */
    public function getStatutLabelAttribute(): string
    {
        return match($this->statut) {
            'en_attente' => 'En attente',
            'complete' => 'Complétée',
            'failed' => 'Échouée',
            'canceled' => 'Annulée',
            'expired' => 'Expirée',
            default => $this->statut
        };
    }
}