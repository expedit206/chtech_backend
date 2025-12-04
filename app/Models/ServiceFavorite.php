<?php

namespace App\Models;

// use App\Models\ServiceFavorite;
use Illuminate\Database\Eloquent\Model;

class ServiceFavorite extends Model
{


    protected $fillable = [
        'user_id',
        'service_id',
    ];


     public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relation avec le service
     */
    public function service()
    {
        return $this->belongsTo(Service::class, 'service_id');
    }

    /**
     * Relation avec le produit
     */
   
}
