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
        Schema::table('produits', function (Blueprint $table) {
            $table->index('is_promoted');
            $table->index('est_actif');
            $table->index('category_id');
            $table->index('ville');
            $table->index('prix');
            $table->index('id_commercant');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->index('id_categorie');
            $table->index('disponibilite');
            $table->index('ville');
            $table->index('prix');
        });

        Schema::table('interaction_produits', function (Blueprint $table) {
            $table->index(['user_id', 'type', 'produit_id'], 'idx_user_interaction');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('produits', function (Blueprint $table) {
            $table->dropIndex(['is_promoted']);
            $table->dropIndex(['est_actif']);
            $table->dropIndex(['category_id']);
            $table->dropIndex(['ville']);
            $table->dropIndex(['prix']);
            $table->dropIndex(['id_commercant']);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropIndex(['id_categorie']);
            $table->dropIndex(['disponibilite']);
            $table->dropIndex(['ville']);
            $table->dropIndex(['prix']);
        });

        Schema::table('interaction_produits', function (Blueprint $table) {
            $table->dropIndex('idx_user_interaction');
        });
    }
};
