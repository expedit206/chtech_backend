<?php
// app/Models/ProduitReview.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProduitReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'produit_id',
        'provider_id',
        'comment',
        'rating',
        'rating_breakdown',
        'is_verified',
        'is_visible',
        'status',
        'provider_response',
        'responded_at'
    ];

    protected $casts = [
        'rating_breakdown' => 'array',
        'is_verified' => 'boolean',
        'is_visible' => 'boolean',
        'responded_at' => 'datetime',
    ];

    /**
     * Relation avec l'utilisateur qui a laissé l'avis
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relation avec le produit
     */
    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class, 'produit_id');
    }

    /**
     * Relation avec le prestataire
     */
    public function provider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'provider_id');
    }

    /**
     * Formater la note avec étoiles
     */
    public function getStarRatingAttribute(): string
    {
        return str_repeat('★', $this->rating) . str_repeat('☆', 5 - $this->rating);
    }


}