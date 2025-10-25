<?php
// app/Models/User.php
namespace App\Models;

use App\Models\Abonnement;
use App\Models\Commercant;
use App\Models\NiveauUser;
use App\Models\Parrainage;
use App\Models\Collaboration;
use App\Models\ProductFavorite;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory; // <---- Ajout de HasApiTokens

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

    public function getAuthPassword()
    {
        return $this->mot_de_passe;
    }

    public function commercant()
    {
        return $this->hasOne(Commercant::class);
    }

    public function collaborations()
    {
        return $this->hasMany(Collaboration::class);
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

    public function favoris_count()
    {
        return $this->hasMany(ProductFavorite::class)->count();
    }

    //favoris
    public function favoris()
    {
        return $this->hasMany(ProductFavorite::class);
    }

 
}