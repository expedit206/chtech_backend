<?php

namespace App\Models;

use App\Models\DeviceToken;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DeviceToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'device_token',
        'browser',
        'device_type',
        'is_active'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }   

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}