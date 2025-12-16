<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('produit_counts', function (Blueprint $table) {
            $table->uuid('produit_id')->primary();
            $table->unsignedInteger('favorites_count')->default(0);
            $table->unsignedInteger('clics_count')->default(0);
            $table->unsignedInteger('contacts_count')->default(0);
            $table->unsignedInteger('partages_count')->default(0);
            $table->timestamps();

            $table->foreign('produit_id')->references('id')->on('produits')->onDelete('cascade');

            $table->index('partages_count');
            $table->index('favorites_count');
            $table->index('clics_count');
            $table->index('contacts_count');
        });
    }

    public function down()
    {
        Schema::dropIfExists('produit_counts');
    }
};