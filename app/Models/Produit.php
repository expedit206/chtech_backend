<?php

// app/Models/Produit.php
namespace App\Models;

use Auth;
use App\Models\User;
use App\Models\Boost;
use App\Models\Revente;
use App\Models\Category;
use App\Models\ProductView;
use App\Models\ProduitCount;
use App\Models\CategoryProduit;
use App\Models\ProductFavorite;
use App\Models\ProduitInteraction;
use App\Traits\InteractionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Produit extends Model
{
    use HasFactory;
    protected $keyType = 'string';
    public $incrementing = false;

    protected $casts = [
        'photos' => 'array', // Cast la colonne JSON en tableau PHP
    ];
    protected $fillable = [
        'id',
        'user_id',
        'category_id',
        'nom',
        'ville',
        'description',
        'prix',
        'quantite',
        'note_moyenne',
        'nombre_avis',
        'revendable',

        'photos',
        'collaboratif',
        'marge_revente_min',
        'condition',
        'original_user_id'
    ];
    // protected $appends = ['favorites_count', 'views_count'];
    
    


    
    public function user()
    {
        return $this->belongsTo(User::class);
    }

        
    public function reventes()
    {
        return $this->hasMany(Revente::class);
    }

  
    public function category()
    {
        return $this->belongsTo(CategoryProduit::class, 'category_id', 'id');
    }


    public function interactions()
    {
        return $this->hasMany(ProduitInteraction::class, 'produit_id', 'id');
    }

    


    public function clics()
    {
        return $this->hasMany(ProduitInteraction::class, 'produit_id')
                    ->where('type', 'clic');
    }

    
   


    public function getFavoritesCountAttribute()
    {
        return $this->favorites()->count();
    }

public function favorites()
{
    return $this->hasMany(ProduitInteraction::class, 'produit_id')
                ->where('type', 'favori');
    // 'user_id' = clé étrangère dans ProduitInteraction
    // 'id' = clé primaire dans User (par défaut, donc optionnel)
}

//isFavoritedByuser

public function isFavorited()
{
    if (!auth()->id()) {
        return false;
    }
    
    return ProduitInteraction::all();
    // return $this->interactions();
    // return $this->interactions()
    //             ->where('user_id', auth()->id())
    //             ->where('type','favori')
    //             ->first();
}

    public function boosts()
    {
        return $this->hasMany(Boost::class, 'produit_id');
    }

    public function counts()
    {
        return $this->hasOne(ProduitCount::class, 'produit_id', 'id');
    }


    public function originaluser()
    {
        return $this->belongsTo(user::class, 'original_user_id');
    }
    
    public function isFavoritedByUser($user = null)
    {
        if ($user) {
            return ProductFavorite::where('produit_id', $this->id)
                ->where('user_id', $user->id)
                ->exists();
        }
        return false; // Retourne false si aucun utilisateur
    }

    // Méthode pour obtenir la date de fin du boost
    public function getBoostedUntilAttribute()
    {
        $boost = $this->boosts()
            ->where('statut', 'actif')
            ->where('end_date', '>', now())
            ->latest('end_date')
            ->first();
        return $boost ? $boost->end_date : null;
    }
    ///reviews
     public function reviews()
    {
        return $this->hasMany(ProduitReview::class);
    }


    // public function increment(string $action){
    //     $this->counts->$action +=1  ;


    // }
}