<?php
// app/Models/User.php
namespace App\Models;

use App\Models\Boost;
use App\Models\Message;
use App\Models\Abonnement;
use App\Models\Commercant;
use App\Models\NiveauUser;
use App\Models\Parrainage;
use Illuminate\Support\Str;
use App\Models\Revente;
use App\Models\ProductFavorite;
use App\Models\JetonTransaction;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use App\Models\DeviceToken;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory; // <---- Ajout de HasApiTokens


       protected $keyType = 'string';
    public $incrementing = false;

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) \Str::uuid();
            }
        });
    }
    protected $fillable = [
        'id',
        'nom',
        'telephone',
        'email',
        'ville',
        'mot_de_passe',
        'role',
        'premium',
        'parrain_id',
        'parrainage_code',
        'jetons',
        'photo',
        'subscription_ends_at'
    ];

    protected $hidden = [
        'mot_de_passe',
        'remember_token', // utile pour masquer si tu utilises remember
    ];



    //uuid string 



    public function getAuthPassword()
    {
        return $this->mot_de_passe;
    }

    public function commercant()
    {
        return $this->hasOne(Commercant::class);
    }    

    public function reventes()
    {
        return $this->hasMany(Revente::class);
    }

    public function filleuls()
    {
        return $this->hasMany(Parrainage::class, 'parrain_id');
    }

    public function parrain()
    {
        return $this->belongsTo(User::class, 'parrain_id');
    }

    public function abonnements()
    {
        return $this->hasMany(Abonnement::class);
    }

    public function jetonsTransactions()
    {
        return $this->hasMany(JetonTransaction::class);
    }

    public function boosts()
    {
        return $this->hasMany(Boost::class);
    }

    public function niveaux_users()
    {
        return $this->hasMany(NiveauUser::class);
    }
    //messages
    public function messages()
    {
        return $this->hasMany(Message::class, 'sender_id');
    }
    // conversation_count
    public function conversations_count()
    {
        // return $this->hasMany(Message::class, 'sender_id')->count();
        return $this->hasMany(Message::class, 'sender_id')->distinct('receiver_id')->count('receiver_id');
    }

      //device token
    public function deviceTokens()
    {
        return $this->hasMany(DeviceToken::class);
    }
 


    public function favoris_count()
    {
        return $this->hasMany(ProductFavorite::class)->count();
    }

    //favoris
    public function favoris()
    {
        return $this->hasMany(ProductFavorite::class);
    }
    
    
    ///serviceFavorites

    public function serviceFavorites(){
        
        return $this->hasMany(ServiceFavorite::class);
    }

    public function hasFavoritedService($serviceId): bool
{
    return $this->serviceFavorites()
                ->where('service_id', $serviceId)
                ->exists();
}




  
    
public function removeFavoriteService($serviceId): bool
{
    return $this->serviceFavorites()
                ->where('service_id', $serviceId)
                ->delete() > 0;
}



public function addFavoriteService($serviceId): ServiceFavorite
{
    return ServiceFavorite::create([
        'user_id' => $this->id,
        'service_id' => $serviceId,
        'favorite_type' => 'service'
    ]);
}
    
    
    ///produitFavorites

    public function produitFavorites(){
        
        return $this->hasMany(ProductFavorite::class);
    }

    public function hasFavoritedProduit($produitId): bool
{
    return $this->produitFavorites()
                ->where('produit_id', $produitId)
                ->exists();
}




  
    
public function removeFavoriteProduit($produitId): bool
{
    return $this->produitFavorites()
                ->where('produit_id', $produitId)
                ->delete() > 0;
}



public function addFavoriteProduit($produitId): ProductFavorite
{
    return ProductFavorite::create([
        'user_id' => $this->id,
        'produit_id' => $produitId,
        'favorite_type' => 'produit'
    ]);
}
}