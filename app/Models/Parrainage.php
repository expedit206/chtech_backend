<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Parrainage extends Model
{
        // Schema::create('parrainages', function (Blueprint $table) {
        //         $table->id();
        //        $table->enum('statut', ['en_attente', 'actif', 'bonus_attribue'])->default('en_attente');
            
        //     // Email de vérification pour le bonus
        //     $table->string('email_verification')->nullable(); // Email où envoyer le code
        //     $table->boolean('email_verifie')->default(false);
        //     $table->string('code_verification', 6)->nullable();
        //     $table->timestamp('code_expire_le')->nullable();
        //     $table->timestamp('email_verifie_le')->nullable();
            
        //     // Bonus
        //     $table->integer('bonus_parrain')->default(3);
        //     $table->boolean('bonus_attribue')->default(false);
        //     $table->timestamp('bonus_attribue_le')->nullable();
            
        //     // Relations
        //     $table->foreignUuid('parrain_id')->references('id')->on('users')->onDelete('cascade');
        //     $table->foreignUuid('filleul_id')->references('id')->on('users')->onDelete('cascade');
            
        //     // Contraintes
        //     $table->unique(['parrain_id', 'filleul_id']);
        //     $table->index(['email_verification', 'code_verification']);
        //     $table->index('statut');
            
        //         $table->timestamps();
        //     });
   
    protected $fillable = ['parrain_id', 'filleul_id', 'code', 'statut',  
    'email_verification', 'email_verifie', 'code_verification', 'code_expire_le',
        'email_verifie_le', 'bonus_parrain', 'bonus_attribue', 'bonus_attribue_le'

];
    protected $casts = [
        'gains' => 'decimal:2',
    ];

    public function parrain()
    {
        return $this->belongsTo(User::class, 'parrain_id');
    }

    public function filleul()
    {
        return $this->belongsTo(User::class, 'filleul_id');
    }

    public function produits()
    {
        return $this->hasManyThrough(Produit::class, User::class, 'id', 'commercant_id', 'filleul_id', 'id');
    }
}