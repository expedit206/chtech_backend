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
        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists('service_counts');
        Schema::dropIfExists('interaction_services');
        Schema::dropIfExists('service_reviews');
        Schema::dropIfExists('services');
        Schema::dropIfExists('category_services');

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // On ne peut pas facilement inverser cette opération car les structures de tables sont complexes
    }
};
