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
        Schema::create('interaction_services', function (Blueprint $table) {
     $table->id();
    $table->foreignUuid('user_id')->constrained()->onDelete('cascade');
    $table->foreignUuid('service_id')->constrained()->onDelete('cascade');
    $table->enum('type', ['clic', 'favori', 'contact', 'partage'])->notNull();
    $table->json('metadata')->nullable();
    $table->timestamps();
    
    $table->unique(['user_id', 'service_id', 'type']);
    $table->index(['service_id', 'user_id','type', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interaction_services');
    }
};
