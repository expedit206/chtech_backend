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
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null'); // Propriétaire / Ambassadeur
            
            // Réduction pour le client
            $table->enum('discount_type', ['percentage', 'fixed_amount'])->default('percentage');
            $table->decimal('discount_value', 10, 2);
            $table->decimal('max_discount_amount', 10, 2)->nullable(); // Plafond en FCFA si %
            $table->decimal('min_order_amount', 10, 2)->default(0); // Seuil minimum
            
            // Commission pour l'ambassadeur (propriétaire)
            $table->enum('commission_type', ['percentage', 'fixed_amount'])->default('percentage');
            $table->decimal('commission_value', 10, 2)->default(0);
            
            // Quotas et contraintes
            $table->integer('usage_limit')->nullable(); // null = illimité
            $table->integer('usage_per_user_limit')->default(1);
            $table->integer('used_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promo_codes');
    }
};
