<?php
// database/migrations/2024_01_01_000000_create_service_reviews_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('service_reviews', function (Blueprint $table) {
            $table->id();
            
            // Références
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignUuid('service_id')->constrained('services')->onDelete('cascade');
            $table->foreignUuid('provider_id')->constrained('users')->onDelete('cascade');
            
            // Contenu de l'avis
            $table->text('comment')->nullable();
            $table->unsignedTinyInteger('rating'); // Note de 1 à 5
            
            // Métadonnées
            
            // Réponse du prestataire
            $table->text('provider_response')->nullable();
            $table->timestamp('responded_at')->nullable();
            
            $table->timestamps();
            
            // Index
            $table->index('service_id');
            $table->index('user_id');
            $table->index('provider_id');
            $table->index('rating');
            $table->index('created_at');
            
            // Un utilisateur ne peut laisser qu'un seul avis par service
            $table->unique(['user_id', 'service_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_reviews');
    }
};