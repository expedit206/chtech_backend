<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserPushToken extends Model
{
    protected $table = 'user_push_tokens';
    
    protected $fillable = [
        'user_id',
        'fcm_token',
        'device_type',
        'browser',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    /**
     * Relation avec l'utilisateur
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope pour les tokens actifs
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}