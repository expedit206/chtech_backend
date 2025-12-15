<?php

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
     
        // database/migrations/YYYY_MM_DD_create_interaction_tables.php
Schema::create('interaction_produits', function (Blueprint $table) {
    $table->id();
    $table->foreignUuid('user_id')->constrained()->onDelete('cascade');
    $table->foreignUuid('produit_id')->constrained()->onDelete('cascade');
    $table->enum('type', ['clic', 'favori', 'contact', 'partage'])->notNull();
    $table->json('metadata')->nullable(); // Pour stocker des données supplémentaires
    $table->timestamps();
    
    $table->unique(['user_id', 'produit_id', 'type']); // Éviter les doublons
    $table->index(['produit_id','user_id', 'type', 'created_at']);
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interaction_produits');
    }
};
