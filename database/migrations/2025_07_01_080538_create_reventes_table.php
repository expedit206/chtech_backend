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
        Schema::create('reventes', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('produit_id')->references('id')->on('produits')->onDelete('cascade');
            $table->foreignUuid('revendeur_id')->references('id')->on('users')->onDelete('cascade');
            $table->decimal('prix_revente', 10, 2);
            $table->enum('statut', ['en_attente', 'valider', 'refuser'])->default('en_attente');
            $table->decimal('gains_totaux', 10, 2)->default(0);
            // unique produit_id revendeur
            
            $table->timestamps();

        });



    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reventes');
    }
};