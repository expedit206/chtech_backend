<?php
// app/Models/Service.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Service extends Model
{
    use HasFactory;

    protected $table = 'services';
    protected $primaryKey = 'id';
    public $timestamps = true;

    protected $fillable = [
        'id_user',
        'id_categorie',
        'titre',
        'description',
        'annees_experience',
        'competences',
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
        return $this->belongsTo(User::class, 'id_user');
    }

    public function categorie()
    {
        return $this->belongsTo(CategoryService::class, 'id_categorie');
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
}