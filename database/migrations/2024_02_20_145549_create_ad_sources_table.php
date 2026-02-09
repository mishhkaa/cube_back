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
        Schema::create('ad_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('platform');
            $table->json('accounts')->nullable();
            $table->json('settings')->nullable();
            $table->string('currency', 3)->nullable();
            $table->boolean('active')->default(false);
            $table->foreignId('user_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('ad_sources_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ad_source_id')
                ->constrained('ad_sources')->restrictOnDelete();
            $table->date('day_start')->nullable();
            $table->date('day_stop')->nullable();
            $table->text('message');
            $table->timestamp('created_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ad_sources');
        Schema::dropIfExists('ad_sources_events');
    }
};
