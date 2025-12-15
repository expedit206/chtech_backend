<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('service_counts', function (Blueprint $table) {
            // $table->foreignUuid('service_id')->references('id')->on('services')->onDelete('cascade')->primary();
    $table->foreignUuid('service_id')->constrained()->onDelete('cascade')->primary();

            // $table->uuid('service_id')->primary();
            $table->unsignedInteger('views_count')->default(0);
            $table->unsignedInteger('favorites_count')->default(0);
            $table->unsignedInteger('clics_count')->default(0);
            $table->unsignedInteger('contacts_count')->default(0);
            $table->unsignedInteger('partages_count')->default(0);
            $table->timestamps();


            $table->index('partages_count');
            $table->index('favorites_count');
            $table->index('views_count');
            $table->index('clics_count');
            $table->index('contacts_count');
        });
    }

    public function down()
    {
        Schema::dropIfExists('service_counts');
    }
};