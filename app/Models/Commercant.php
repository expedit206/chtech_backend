<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class Commercant extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'nom_boutique',
        'nom_responsable',
        'telephone',
        'marche',
        'numero_boutique',
        'statut',
        'notes',
    ];

    public function produits()
    {
        return $this->hasMany(Produit::class);
    }
}
