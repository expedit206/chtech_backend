<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('parrainages', function (Blueprint $table) {
                $table->id();
               $table->enum('statut', ['en_attente', 'actif', 'bonus_attribue'])->default('en_attente');
            
            // Email de vérification pour le bonus
            $table->string('email_verification')->nullable(); // Email où envoyer le code
            $table->boolean('email_verifie')->default(false);
            $table->string('code_verification', 6)->nullable();
            $table->timestamp('code_expire_le')->nullable();
            $table->timestamp('email_verifie_le')->nullable();
            
            // Bonus
            $table->integer('bonus_parrain')->default(3);
            $table->boolean('bonus_attribue')->default(false);
            $table->timestamp('bonus_attribue_le')->nullable();
            
            // Relations
            $table->foreignUuid('parrain_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreignUuid('filleul_id')->references('id')->on('users')->onDelete('cascade');
            
                        $table->boolean('is_read')->default(false);

            // Contraintes
            $table->unique(['parrain_id', 'filleul_id']);
            $table->index(['email_verification', 'code_verification']);
            $table->index('statut');
                        $table->index(['id', 'is_read', 'created_at']);

            
                $table->timestamps();
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parrainages');
    }
};