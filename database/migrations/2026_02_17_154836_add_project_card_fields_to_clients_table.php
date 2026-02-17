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
        // Додаємо тільки відсутні поля через raw SQL для уникнення конфліктів
        $columns = [];
        try {
            $existingColumns = \DB::select("SHOW COLUMNS FROM clients");
            $columns = array_column($existingColumns, 'Field');
        } catch (\Exception $e) {
            // Якщо не вдалося отримати список колонок, продовжуємо
        }
        
        Schema::table('clients', function (Blueprint $table) use ($columns) {
            // Етап 2: Дані про команду - додаємо тільки якщо немає
            if (!in_array('team_lead_id', $columns)) {
                $table->foreignId('team_lead_id')->nullable()->after('description')->constrained('users')->nullOnDelete();
            }
            if (!in_array('ppc_team_service', $columns)) {
                $table->string('ppc_team_service')->nullable()->after('team_lead_id');
            }
            
            // Додаємо унікальний індекс для project_id якщо його немає
            if (in_array('project_id', $columns)) {
                try {
                    \DB::statement('ALTER TABLE clients ADD UNIQUE INDEX project_id_unique (project_id)');
                } catch (\Exception $e) {
                    // Індекс вже існує, ігноруємо помилку
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropForeign(['team_lead_id']);
            $table->dropColumn([
                'project_id',
                'ad_networks',
                'status',
                'start_date',
                'end_date',
                'creative_sponsor',
                'direction',
                'sub_niche',
                'scaling',
                'project_status',
                'ad_account_identifiers',
                'team_lead_id',
                'ppc_team_service',
            ]);
        });
    }
};
