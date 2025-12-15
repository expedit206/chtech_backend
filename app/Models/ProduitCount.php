<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProduitCount extends Model
{

    protected $table = 'produit_counts';
    protected $primaryKey = 'produit_id';
    public $incrementing = false; // Puisque produit_id est un UUID
    protected $keyType = 'string';  
    protected $fillable = ['produit_id','contacts_count', 'favorites_count', 'clics_count', 'partages_count'];

}