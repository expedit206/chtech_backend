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
        Schema::create('badge_unreads', function (Blueprint $table) {
   $table->id();
            $table->foreignUuid('user_id')->references('id')->on('users')->onDelete('cascade')->unique();
            $table->integer('messages')->default(0);
            $table->integer('reventes')->default(0);
            $table->integer('parrainages')->default(0);
            
            // Colonne générée pour le total
            $table->integer('total_unread')->storedAs('messages + reventes + parrainages');
            
            $table->timestamps();

            // Clé étrangère

            // Index
            $table->index('user_id');
            $table->index('messages');
            $table->index('reventes');
            $table->index('parrainages');
            $table->index('total_unread');           
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('badge_unreads');
    }
};
