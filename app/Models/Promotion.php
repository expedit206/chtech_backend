<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Promotion extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'produit_id',
        'total_clicks',
        'used_clicks',
        'remaining_clicks',
        'cost_per_click',
        'total_cost',
        'status',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function produit()
    {
        return $this->belongsTo(Produit::class);
    }

    // Scopes simples
    public function scopeActive($query)
    {
        return $query->where('status', 'active')->where('remaining_clicks', '>', 0);
    }

    // Méthodes utilitaires
    public function getProgressPercentage()
    {
        if ($this->total_clicks == 0) return 0;
        return round(($this->used_clicks / $this->total_clicks) * 100);
    }

    public function isActive()
    {
        return $this->status === 'active' && $this->remaining_clicks > 0;
    }

    public function recordClick()
    {
        $this->used_clicks += 1;
        $this->remaining_clicks -= 1;
        
        // Si plus de clics, terminer la promotion
        if ($this->remaining_clicks <= 0) {
            $this->status = 'completed';
            $this->ended_at = now();
            
            // Mettre à jour le produit
            $this->produit->update([
                'is_promoted' => false,
                'promotion_ends_at' => null
            ]);
        }
        
        $this->save();
    }

    public function stop()
    {
        $this->status = 'stopped';
        $this->ended_at = now();
        
        // Calculer le remboursement
        $refundAmount = $this->remaining_clicks * $this->cost_per_click;
        
        // Rembourser l'utilisateur
        $this->user->tokens += $refundAmount;
        $this->user->save();
        
        // Désactiver la promotion sur le produit
        $this->produit->update([
            'is_promoted' => false,
            'promotion_ends_at' => null
        ]);
        
        $this->save();
        
        return $refundAmount;
    }
}