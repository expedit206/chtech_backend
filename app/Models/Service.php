<?php
// app/Models/Service.php

namespace App\Models;

use App\Models\User;
use App\Models\ServiceReview;
use App\Models\CategoryService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Service extends Model
{
    use HasFactory;

    public $timestamps = true;

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
        'user_id',
        'category_id',
        'titre',
        'description',
        'annees_experience',
        'competences',
        'note_moyenne',
        'nombre_avis',
        'localisation',
        'ville',
        'disponibilite',
        'images'
    ];

    protected $casts = [
        'annees_experience' => 'integer',
        'competences' => 'array',
        'images' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relations
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    public function reviews()
    {
        return $this->hasMany(ServiceReview::class);
    }

    public function category()
    {
        return $this->belongsTo(CategoryService::class,'category_id');
    }

    // Scopes
    public function scopeDisponible($query)
    {
        return $query->where('disponibilite', 'disponible');
    }

    // Accessors
    public function getCompetencesListeAttribute()
    {
        return $this->competences ? implode(', ', $this->competences) : '';
    }

    public function calculateAverageRating(): void
{
    $average = $this->reviews->avg('rating');
    $this->update([
        'note_moyenne' => round($average, 1),
        'nombre_avis' => $this->reviews->count()
    ]);
}

    public function counts()
    {
        return $this->hasOne(ServiceCount::class, 'service_id', 'id');
    }

}