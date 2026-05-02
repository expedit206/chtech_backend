<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            // JSON column to store cart items for type='cart' messages
            // Stored as: [{ productId, name, slug, priceRaw, quantity, image, vendorId }]
            $table->json('cart_data')->nullable()->after('attachment_url');
            
            // Index on type to speed up filtering cart messages in a conversation
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropIndex(['type']);
            $table->dropColumn('cart_data');
        });
    }
};
