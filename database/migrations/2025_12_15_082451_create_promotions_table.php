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
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            
            // Relations essentielles
            $table->foreignUuid('user_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('produit_id')->constrained()->onDelete('cascade');
            
            // SEULEMENT les champs essentiels
            $table->integer('total_clicks')->default(0);      // Nombre de clics achetés
            $table->integer('used_clicks')->default(0);       // Clics déjà utilisés
            $table->integer('remaining_clicks')->default(0);  // Clics restants
            $table->decimal('cost_per_click')->default(0.1);    // Coût par clic en jetons
            $table->integer('total_cost')->default(0);        // Coût total en jetons
            
            // Statut simple
            $table->enum('status', ['active', 'paused', 'completed', 'stopped'])->default('active');
            
            // Dates principales
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            
            // Index pour les performances
            $table->index(['produit_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index(['remaining_clicks', 'status']);
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotions');
    }
};
