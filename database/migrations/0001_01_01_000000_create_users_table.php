<?php
// database/migrations/2025_07_01_000001_create_users_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('nom');
            $table->string('email')->nullable()->unique();
            $table->string('telephone')->unique()-> nullable();
            $table->string('parrainage_code', 50)->nullable()->unique();
            $table->string('ville')->nullable();
            $table->string('quartier')->nullable();
            // date naissance
            $table->date('date_naissance')->nullable(); 
            $table->string('mot_de_passe');
            $table->string('photo')->nullable();
            $table->boolean('premium')->default(false);
            $table->timestamp('subscription_ends_at')->nullable();
// quartier
            $table->boolean('est_verifie')->default(false);
            $table->string('verification_code')->nullable();

            // Relation auto-référencée
            $table->foreignUuid('parrain_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('google_id')->nullable()->unique();

            $table->unsignedInteger('jetons')->default(0);
            $table->decimal('solde', 10, 2)->default(0);

            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('token_expires_at')->nullable();

            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignUuid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down()
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
}