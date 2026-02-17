<?php

namespace Database\Seeders;

use App\Models\Request;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class RequestSeeder extends Seeder
{
    /**
     * Seed тестові дані для графіків
     */
    public function run(): void
    {
        // Видаляємо старі тестові дані (опціонально)
        Request::query()->where('action', 'test')->delete();

        $now = Carbon::now();
        $startDate = $now->copy()->subDays(7)->startOfDay();

        // Генеруємо дані за останні 7 днів з інтервалом 1 година (для меншої кількості записів)
        $current = $startDate->copy();
        $batch = [];
        $batchSize = 100;
        
        while ($current->lte($now)) {
            // Синусоїдальна функція для красивого графіка з піками
            $hoursFromStart = $current->diffInHours($startDate);
            $baseValue = 15 + sin($hoursFromStart / 12) * 12; // Базове значення 3-27
            $randomVariation = rand(-3, 3);
            $count = max(3, (int)($baseValue + $randomVariation));

            // Створюємо кілька записів для цього часового інтервалу
            // Різноманітні IP для різних країн (для карти світу)
            $testIps = [
                '127.0.0.1', '192.168.1.' . rand(1, 255), // Локальні
                '8.8.8.' . rand(1, 255), '1.1.1.' . rand(1, 255), // US
                '185.159.157.' . rand(1, 255), '91.198.174.' . rand(1, 255), // UA
                '178.63.' . rand(1, 255) . '.' . rand(1, 255), // DE
                '5.135.' . rand(1, 255) . '.' . rand(1, 255), // PL
                '31.177.' . rand(1, 255) . '.' . rand(1, 255), // GB
            ];
            
            for ($i = 0; $i < $count; $i++) {
                $batch[] = [
                    'action' => 'test',
                    'method' => ['GET', 'POST', 'PUT', 'DELETE'][rand(0, 3)],
                    'path' => ['/api/users', '/api/clients', '/api/integrations', '/api/dashboard'][rand(0, 3)],
                    'status' => [200, 200, 200, 200, 201, 400, 404, 500][rand(0, 7)], // Більшість успішних
                    'message' => 'Test request',
                    'query' => '[]',
                    'post' => '[]',
                    'time' => round(rand(10, 500) / 100, 2),
                    'ip' => $testIps[array_rand($testIps)],
                    'referer_url' => 'http://localhost:3000',
                    'user_agent' => 'Mozilla/5.0 (Test Browser)',
                    'created_at' => $current->copy()->addSeconds(rand(0, 3599)),
                ];

                // Вставляємо батчами для економії пам'яті
                if (count($batch) >= $batchSize) {
                    Request::insert($batch);
                    $batch = [];
                }
            }

            $current->addHour();
        }

        // Вставляємо залишок
        if (!empty($batch)) {
            Request::insert($batch);
        }

        $this->command->info('Створено тестові запити за останні 7 днів');
    }
}
