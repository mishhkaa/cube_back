<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->text('description')->nullable()->after('niche');
        });

        Schema::table('ad_sources', function (Blueprint $table) {
            $table->foreignId('client_id')->nullable()->after('user_id')
                ->constrained('clients')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ad_sources', function (Blueprint $table) {
            $table->dropConstrainedForeignId('client_id');
        });
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
};
