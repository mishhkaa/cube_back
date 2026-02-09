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
        Schema::create('requests', function (Blueprint $table) {
            $table->id();
            $table->string('action', 50)->nullable()->index();
            $table->string('method', 10)->nullable();
            $table->string('path')->nullable();
            $table->integer('status')->nullable();
            $table->text('message')->nullable();
            $table->json('query')->nullable();
            $table->json('post')->nullable();
            $table->double('time')->nullable();
            $table->ipAddress('ip')->nullable();
            $table->string('referer_url')->nullable();
            $table->text('user_agent')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requests');
    }
};
