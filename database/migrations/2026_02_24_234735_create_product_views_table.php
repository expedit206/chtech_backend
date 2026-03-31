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
        Schema::create('product_views', function (Blueprint $table) {
            $table->id();
            $table->uuid('produit_id')->index();
            $table->uuid('user_id')->nullable()->index();
            $table->timestamps();

            // Optionnel : index unique pour éviter les doublons si souhaité par le controller
            // $table->unique(['produit_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_views');
    }
};
