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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->unique();
            $table->string('avatar_url')->nullable();
            $table->string('google_id')->nullable();
            $table->string('slack_id')->nullable();
            $table->text('fb_access_token')->nullable();
            $table->text('tiktok_access_token')->nullable();
            $table->text('x_token_data')->nullable();
            $table->boolean('active');
            $table->json('permissions')->nullable();
            $table->string('password')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
