<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jeton_transactions', function (Blueprint $table) {
            $table->id();
            
            // Relations
            $table->foreignId('acheteur_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('vendeur_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->foreignId('offer_id')->nullable()->constrained('jeton_offers')->onDelete('cascade');
            
            // Type de transaction
            $table->enum('type', ['marketplace', 'platform']);
            
            // Détails jetons
            $table->integer('nombre_jetons')->unsigned();
            $table->decimal('prix_unitaire', 10, 2);
            $table->decimal('montant_total', 10, 2);
            $table->decimal('commission_plateforme', 10, 2)->default(0);
            $table->decimal('montant_net_vendeur', 10, 2)->default(0);
            
            // Paiement NotchPay
            $table->string('notchpay_reference')->unique();
            $table->json('notchpay_metadata')->nullable();
            
            // Statut
            $table->enum('statut', ['pending', 'complete', 'failed', 'canceled','expired'])->default('pending');
            
            // Timestamps
            $table->timestamp('date_transaction')->useCurrent();
            $table->timestamps();
            
            // Index
            $table->index(['type', 'statut']);
            $table->index('notchpay_reference');
            $table->index('acheteur_id');
            $table->index('vendeur_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jeton_transactions');
    }
};