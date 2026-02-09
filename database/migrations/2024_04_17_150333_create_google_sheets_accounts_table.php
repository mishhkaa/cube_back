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
        Schema::create('google_sheet_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('spreadsheet_id');
            $table->string('sheet_id');
            $table->boolean('has_header')->default(false);
            $table->boolean('active')->default(false);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('google_sheet_accounts');
    }
};
