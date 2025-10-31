<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('premium_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->enum('type_abonnement', ['mensuel', 'annuel']);
            $table->decimal('montant', 10, 2);
            $table->string('transaction_id_notchpay')->unique();
            $table->enum('statut', ['en_attente','pending', 'complete', 'failed', 'canceled', 'expired'])->default('en_attente');
            $table->json('notchpay_metadata')->nullable();
            $table->timestamp('date_transaction')->nullable();
            $table->timestamps();
            
            // Index
            $table->index(['user_id', 'statut']);
            $table->index('transaction_id_notchpay');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('premium_transactions');
    }
};