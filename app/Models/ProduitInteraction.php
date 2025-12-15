<?php

namespace App\Models;

use App\Models\User;
use App\Models\Produit;
use Illuminate\Database\Eloquent\Model;

class ProduitInteraction extends Model
{
    protected $table = 'interaction_produits';
    protected $fillable = ['user_id', 'produit_id', 'type', 'metadata'];
    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function produit()
    {
        return $this->belongsTo(Produit::class);
    }
}
