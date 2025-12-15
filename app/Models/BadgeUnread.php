<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BadgeUnread extends Model
{


    protected $table = 'badge_unreads';
    protected $fillable = ['user_id', 'messages', 'reventes', 'parrainages']; 

}
