<?php

namespace App\Models;

use App\Models\User;
use App\Models\Service;
use Illuminate\Database\Eloquent\Model;

class ServiceInteraction extends Model
{
    protected $table = 'interaction_services';
    protected $fillable = ['user_id', 'service_id', 'type', 'metadata'];
    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}