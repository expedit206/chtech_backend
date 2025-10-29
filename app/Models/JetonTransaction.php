<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JetonTransaction extends Model
{
    /**
     * Les attributs qui sont assignables en masse.
     *
     * @var array
     */
    protected $table = 'jeton_transactions';
    
    protected $fillable = [
        'acheteur_id',
        'vendeur_id',
        'offer_id',
        'type',
        'nombre_jetons',
        'prix_unitaire',
        'montant_total',
        'commission_plateforme',
        'montant_net_vendeur',
        'notchpay_reference',
        'notchpay_metadata',
        'statut',
        'date_transaction',
    ];

    /**
     * Les attributs à caster.
     *
     * @var array
     */
    protected $casts = [
        'prix_unitaire' => 'decimal:2',
        'montant_total' => 'decimal:2',
        'commission_plateforme' => 'decimal:2',
        'montant_net_vendeur' => 'decimal:2',
        'notchpay_metadata' => 'array',
        'date_transaction' => 'datetime',
    ];

    /**
     * Les valeurs par défaut.
     *
     * @var array
     */
    protected $attributes = [
        'statut' => 'en_attente',
        'commission_plateforme' => 0,
        'montant_net_vendeur' => 0,
    ];

    /**
     * Indique si les timestamps sont activés.
     *
     * @var bool
     */
    public $timestamps = true;

    /**
     * Relation avec l'acheteur.
     */
    public function acheteur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acheteur_id');
    }

    /**
     * Relation avec le vendeur.
     */
    public function vendeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendeur_id');
    }

    /**
     * Relation avec l'offre (pour les transactions marketplace).
     */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(JetonOffer::class, 'offer_id');
    }

    /**
     * Scope pour les transactions en attente.
     */
    public function scopeEnAttente($query)
    {
        return $query->where('statut', 'en_attente');
    }

    /**
     * Scope pour les transactions confirmées.
     */
    public function scopeConfirme($query)
    {
        return $query->where('statut', 'confirmé');
    }

    /**
     * Scope pour les transactions échouées.
     */
    public function scopeEchec($query)
    {
        return $query->where('statut', 'echec');
    }

    /**
     * Scope pour les transactions de type marketplace.
     */
    public function scopeMarketplace($query)
    {
        return $query->where('type', 'marketplace');
    }

    /**
     * Scope pour les transactions de type platform.
     */
    public function scopePlatform($query)
    {
        return $query->where('type', 'platform');
    }

    /**
     * Vérifie si la transaction est en attente.
     */
    public function isEnAttente(): bool
    {
        return $this->statut === 'en_attente';
    }

    /**
     * Vérifie si la transaction est confirmée.
     */
    public function isConfirme(): bool
    {
        return $this->statut === 'confirmé';
    }

    /**
     * Vérifie si la transaction est un achat marketplace.
     */
    public function isMarketplace(): bool
    {
        return $this->type === 'marketplace';
    }

    /**
     * Vérifie si la transaction est un achat direct plateforme.
     */
    public function isPlatform(): bool
    {
        return $this->type === 'platform';
    }

    /**
     * Marquer la transaction comme confirmée.
     */
    public function markAsConfirmed(): bool
    {
        return $this->update([
            'statut' => 'confirmé',
            'date_transaction' => now(),
        ]);
    }

    /**
     * Marquer la transaction comme échouée.
     */
    public function markAsFailed(?string $errorMessage = null): bool
    {
        return $this->update([
            'statut' => 'echec',
            'notchpay_metadata' => array_merge(
                $this->notchpay_metadata ?? [],
                ['error_message' => $errorMessage]
            ),
        ]);
    }

    /**
     * Obtenir le libellé du statut.
     */
    public function getStatutLabelAttribute(): string
    {
        return match($this->statut) {
            'en_attente' => 'En attente',
            'confirmé' => 'Confirmé',
            'echec' => 'Échec',
            'annulé' => 'Annulé',
            default => $this->statut
        };
    }

    /**
     * Obtenir le libellé du type.
     */
    public function getTypeLabelAttribute(): string
    {
        return match($this->type) {
            'marketplace' => 'Marketplace',
            'platform' => 'Plateforme',
            default => $this->type
        };
    }
}