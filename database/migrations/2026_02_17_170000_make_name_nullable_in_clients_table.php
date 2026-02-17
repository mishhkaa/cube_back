<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Змінюємо колонку name на nullable або з дефолтним значенням
            $table->string('name')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            // Повертаємо назад до NOT NULL з дефолтним значенням
            $table->string('name')->default('Без назви')->change();
        });
    }
};
