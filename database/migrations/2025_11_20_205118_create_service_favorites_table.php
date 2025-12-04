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
        Schema::create('service_favorites', function (Blueprint $table) {
            $table->id();
                 // Références
            $table->foreignUuid('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignUuid('service_id')->constrained('services')->onDelete('cascade');
            $table->timestamps();


                      
            // Index
            $table->index('user_id');
            $table->index('service_id');
            $table->index('created_at');
            
            // Un utilisateur ne peut avoir qu'un seul favori par élément
            $table->unique(['user_id', 'service_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_favorites');
    }
};
