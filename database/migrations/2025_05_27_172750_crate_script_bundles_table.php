<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('script_bundles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('utm')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('scriptables', function (Blueprint $table) {
            $table->foreignId('script_bundle_id')->constrained()->onDelete('cascade');
            $table->morphs('scriptable');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('script_bundles');
        Schema::dropIfExists('scriptables');
    }
};
