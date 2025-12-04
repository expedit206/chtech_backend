<?php

namespace App\Models;

use App\Models\Produit;
use App\Models\ServiceFavorite;
use Illuminate\Database\Eloquent\Model;

class ProductFavorite extends Model
{

    protected $fillable = ['produit_id', 'user_id'];


       public function produit()
    {
        return $this->belongsTo(Produit::class);
    }

}