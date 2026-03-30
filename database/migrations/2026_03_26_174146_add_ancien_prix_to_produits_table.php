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
            $table->integer('ancien_prix')->nullable()->after('prix')->comment('Used to show crossed out old price');
            if (Schema::hasColumn('produits', 'is_promoted')) {
                $table->dropColumn('is_promoted');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('produits', function (Blueprint $table) {
            $table->dropColumn('ancien_prix');
            $table->boolean('is_promoted')->default(false);
        });
    }
};
