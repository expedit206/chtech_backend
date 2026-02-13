<?php

namespace App\Models;

use App\Models\User;
use App\Models\Produit;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{


    protected $fillable = ['sender_id','type', 'receiver_id', 'product_id', 'content', 'attachment_url'];
    // table
    protected $table = 'messages';
    


    
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

    
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }
    public function product()
    {
        return $this->belongsTo(Produit::class, 'product_id');
    }
}