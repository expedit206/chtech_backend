<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->text('bio')->nullable()->after('ville');
            $table->string('website')->nullable()->after('bio');
            $table->string('whatsapp')->nullable()->after('website');
            $table->string('instagram')->nullable()->after('whatsapp');
            $table->string('cover')->nullable()->after('photo');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['bio', 'website', 'whatsapp', 'instagram', 'cover']);
        });
    }
};
