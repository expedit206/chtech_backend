<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoryProduit extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['nom', 'image', 'parent_id'];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) \Str::uuid();
            }
        });
    }
}
