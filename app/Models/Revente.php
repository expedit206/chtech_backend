<?php

// app/Models/Revente.php
namespace App\Models;

use App\Models\User;
use App\Models\Produit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Revente extends Model
{
    use HasFactory;
    // protected $keyType = 'string';
    // public $incrementing = false;

    protected $fillable = [
        'id',
        'produit_id',
        'revendeur_id',
        'prix_revente',
        'statut',
        'gains_totaux'
    ];

    protected $casts = [
        'statut' => 'string', // Pour gérer l'enum ['en_attente', 'validée', 'refusée']
    ];

    public function produit()
    {
        return $this->belongsTo(Produit::class);
    }

    public function revendeur()
    {
        return $this->belongsTo(User::class, 'revendeur_id');
    }
}