<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'total_amount',
        'status',
        'payment_status',
        'delivery_address',
        'contact_phone',
        'promo_code_id',
        'promo_code',
        'discount_amount',
        'commission_amount',
    ];

    protected $casts = [
        'total_amount' => 'float',
        'discount_amount' => 'float',
        'commission_amount' => 'float',
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function promoCode()
    {
        return $this->belongsTo(PromoCode::class);
    }

    public function promoCodeUsage()
    {
        return $this->hasOne(PromoCodeUsage::class);
    }

    public function commissions()
    {
        return $this->hasMany(Commission::class);
    }
}
