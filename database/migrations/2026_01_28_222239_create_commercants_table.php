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
        Schema::create('commercants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nom_boutique');
            $table->string('nom_responsable');
            $table->string('telephone');
            $table->string('marche');
            $table->string('numero_boutique')->nullable();
            $table->enum('statut', ['prospect', 'partenaire', 'inactif'])->default('prospect');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commercants');
    }
};
