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
        Schema::create('produits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreignUuid('original_user_id')->nullable()->references('id')->on('users')->onDelete('cascade');
            $table->string('nom');
            $table->text('description')->nullable();
            $table->integer('prix')->default(0);
            $table->integer('quantite')->default(1);
            $table->foreignUuid('category_id')->nullable()->constrained('category_produits')->onDelete('cascade');
            $table->string('ville')->nullable();
            $table->json('photos')->nullable(); // Remplace photo_url par photos (JSON)
             
                // Métadonnées
                $table->decimal('note_moyenne', 3, 2)->default(0.00);
                $table->integer('nombre_avis')->default(0);
                
            
            $table->enum('condition', ['neuf', 'occasion', 'reconditionne'])->default('neuf');
            $table->boolean('revendable')->default(false);
            $table->decimal('marge_revente_min', 10, 2)->nullable();
    // marge_revente_max DECIMAL(5,2) NULL, -- Pourcentage maximum autorisé
                    // localisation VARCHAR(255) NOT NULL,
                    // est_actif BOOLEAN DEFAULT TRUE,
                    $table->json('localisation')->nullable(); // Pour stocker des données de localisation plus détaillées
                $table->boolean('est_actif')->default(true);

                                $table->boolean('is_promoted')->default(false);

            $table->timestamps();

            // $table->index('category_id');
            
                $table->index('user_id');
                $table->index('category_id');
            $table->index('prix');
            $table->index('ville');
            $table->index('revendable');
            $table->index('created_at');
        });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('produits');
    }
};