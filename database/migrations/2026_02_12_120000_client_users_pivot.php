<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('client_user')) {
            Schema::create('client_user', function (Blueprint $table) {
                $table->id();
                $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->unique(['client_id', 'user_id']);
            });
        }

        // Migrate existing user_id to pivot (якщо колонка існує)
        if (Schema::hasColumn('clients', 'user_id')) {
            foreach (DB::table('clients')->whereNotNull('user_id')->get() as $row) {
                // Перевіряємо чи запис вже не існує в pivot
                $exists = DB::table('client_user')
                    ->where('client_id', $row->id)
                    ->where('user_id', $row->user_id)
                    ->exists();
                
                if (!$exists) {
                    DB::table('client_user')->insert([
                        'client_id' => $row->id,
                        'user_id' => $row->user_id,
                    ]);
                }
            }

            Schema::table('clients', function (Blueprint $table) {
                if (Schema::hasColumn('clients', 'user_id')) {
                    $table->dropConstrainedForeignId('user_id');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('niche')->constrained('users')->nullOnDelete();
        });
        foreach (DB::table('client_user')->orderBy('id')->get() as $row) {
            DB::table('clients')->where('id', $row->client_id)->update(['user_id' => $row->user_id]);
        }
        Schema::dropIfExists('client_user');
        Schema::table('clients', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
