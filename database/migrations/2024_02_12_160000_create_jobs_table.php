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
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('data_jobs', function (Blueprint $table) {
            $table->id();
            $table->enum('status',['new', 'processing', 'done', 'warning', 'error'])
                ->default('new')->index();
            $table->string('queue', 50)->nullable()->index();
            $table->string('event')->nullable()->index();
            $table->string('action')->nullable();
            $table->json('payload')->nullable();
            $table->longText('message')->nullable();
            $table->json('response')->nullable();
            $table->foreignId('request_id')->nullable()->constrained('requests')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['queue', 'event']);
            $table->index(['queue', 'event', 'action', 'created_at']);
            $table->index(['created_at']);
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('data_jobs');
        Schema::dropIfExists('failed_jobs');
    }
};
