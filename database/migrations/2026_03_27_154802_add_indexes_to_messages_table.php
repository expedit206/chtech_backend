<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ajout des index composites pour optimiser les requêtes du ChatController.
     *
     * Avant : O(N) requêtes en boucle sans index → très lent.
     * Après : Toutes les recherches de messages par (sender+receiver+product) sont O(log N).
     */
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // Index composite pour la requête groupBy (conversations list)
            // Couvre : WHERE sender_id=? OR receiver_id=? GROUP BY interlocutor_id, product_id
            $table->index(['sender_id', 'receiver_id', 'product_id', 'created_at'], 'idx_msg_conv_product_time');

            // Index pour les messages non lus (unread count par sender+product)
            $table->index(['receiver_id', 'is_read', 'sender_id', 'product_id'], 'idx_msg_unread_batch');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex('idx_msg_conv_product_time');
            $table->dropIndex('idx_msg_unread_batch');
        });
    }
};
