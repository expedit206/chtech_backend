<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceCount extends Model
{
 protected $table = 'service_counts';
    protected $primaryKey = 'service_id';
    public $incrementing = false; // Puisque service_id est un UUID
    protected $keyType = 'string';  
    protected $fillable = ['service_id','contacts_count', 'favorites_count', 'clics_count', 'partages_count'];
}
