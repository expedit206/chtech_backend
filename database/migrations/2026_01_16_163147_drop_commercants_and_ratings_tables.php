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
        Schema::dropIfExists('commercant_ratings');
        Schema::dropIfExists('commercants');
    }

    public function down(): void
    {
        Schema::create('commercants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nom');
            $table->timestamps();
        });

        Schema::create('commercant_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('commercant_id')->constrained()->onDelete('cascade');
            $table->integer('rating');
            $table->timestamps();
        });
    }
};
