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
        Schema::create('pixels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('source', 50)->index();
            $table->string('currency', 3)->nullable();
            $table->string('pixel_id', 50);
            $table->text('access_token');
            $table->boolean('active')->default(false);
            $table->boolean('testing')->default(true);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('tracking_users', function (Blueprint $table) {
            $table->string('id')->unique();
            $table->json('data');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pixels');
        Schema::dropIfExists('tracking_users');
    }
};
