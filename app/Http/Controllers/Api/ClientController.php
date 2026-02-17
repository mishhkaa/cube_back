<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdSource;
use App\Models\Client;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    public function index(Request $request): array
    {
        $query = Client::query()
            ->with('users:id,name,email')
            ->with('teamLead:id,name,email')
            ->withCount('adSources');

        if ($search = $request->query('search')) {
            $query->where(function (Builder $q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('project_id', 'like', "%{$search}%")
                    ->orWhere('niche', 'like', "%{$search}%")
                    ->orWhere('sub_niche', 'like', "%{$search}%")
                    ->orWhere('link', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }
        if ($unit = $request->query('unit')) {
            $query->where('unit', (int) $unit);
        }
        if ($niche = $request->query('niche')) {
            $query->where('niche', $niche);
        }
        if ($direction = $request->query('direction')) {
            $query->where('direction', $direction);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $paginator = $query->orderBy('created_at', 'desc')->paginate(
            $request->query('per_page', 20)
        );

        return [
            'data' => $paginator->items(),
            'total' => $paginator->total(),
        ];
    }

    public function store(Request $request): array
    {
        $data = $request->validate([
            // Базові поля - обов'язкові на старті
            'name' => 'nullable|string|max:255',
            'link' => 'nullable|string|max:500',
            'unit' => 'nullable|integer|in:1,2,3', // Може бути не призначений відразу
            'niche' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            
            // Project Card - Етап 1: Дані про проєкт
            'ad_networks' => 'nullable|array', // Можна обрати платформи, але кабінети ще не підключені
            'ad_networks.*' => 'required_with:ad_networks|string|in:google,tiktok,facebook,x',
            'status' => 'nullable|string|max:100', // Статус відомий відразу (до_початку)
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'creative_sponsor' => 'nullable|in:за_наш_рахунок,за_їх_рахунок,комбіновано', // Може бути невідомо
            'direction' => 'nullable|in:leads,purchases', // Напрямок визначається відразу
            'niche' => 'nullable|string|max:100', // Може бути невідомо відразу
            'sub_niche' => 'nullable|string|max:100', // Може бути невідомо відразу
            'scaling' => 'nullable|array', // Може бути невідомо на початку
            'scaling.enabled' => 'nullable|boolean',
            'scaling.metric' => 'nullable|required_if:scaling.enabled,true|string|in:CPA,ROAS,Share',
            'scaling.currency' => 'nullable|required_if:scaling.enabled,true|string|in:UAH,USD,EUR',
            'scaling.kpi_value' => 'nullable|required_if:scaling.enabled,true|numeric|min:0',
            'project_status' => 'nullable|in:paid,free', // Може бути невідомо (бартер)
            'ad_account_identifiers' => 'nullable|array', // ❌ НЕ можна заповнити поки не підключили кабінети
            'ad_account_identifiers.*' => 'required_with:ad_account_identifiers|string',
            
            // Етап 2: Дані про команду - опціональні на початку
            'team_lead_id' => 'nullable|integer|exists:users,id', // ❌ Команда може бути не сформована
            'ppc_team_service' => 'nullable|string|max:255', // ❌ Може бути не визначена
            'user_ids' => 'nullable|array', // ❌ Відповідальні можуть бути не призначені
            'user_ids.*' => 'integer|exists:users,id',
            'ad_source_ids' => 'nullable|array', // ❌ НЕ можна заповнити поки не налаштували джерела
            'ad_source_ids.*' => 'integer|exists:ad_sources,id',
        ]);

        // Перевіряємо та очищаємо name - завжди має бути значення
        if (isset($data['name']) && $data['name'] !== null) {
            $data['name'] = trim($data['name']);
            if (empty($data['name'])) {
                $data['name'] = 'Без назви';
            }
        } else {
            // Якщо name не передано, встановлюємо дефолтне значення
            $data['name'] = 'Без назви';
        }

        // Генеруємо project_id якщо не вказано
        if (empty($data['project_id'])) {
            $data['project_id'] = $this->generateProjectId($data['name']);
        }

        // Конвертуємо порожні масиви в null
        if (isset($data['ad_networks']) && empty($data['ad_networks'])) {
            $data['ad_networks'] = null;
        }
        if (isset($data['ad_account_identifiers']) && empty($data['ad_account_identifiers'])) {
            $data['ad_account_identifiers'] = null;
        }
        if (isset($data['scaling']) && empty($data['scaling'])) {
            $data['scaling'] = null;
        }

        $adSourceIds = $data['ad_source_ids'] ?? null;
        $userIds = $data['user_ids'] ?? null;
        unset($data['ad_source_ids'], $data['user_ids']);

        $client = Client::query()->create($data);
        
        // Синхронізуємо тільки якщо передано
        if ($userIds !== null && !empty($userIds)) {
            $client->users()->sync($userIds);
        }
        if ($adSourceIds !== null && !empty($adSourceIds)) {
            $this->syncAdSources($client, $adSourceIds);
        }

        return $client->fresh()->load(['users:id,name,email', 'adSources:id,name,platform,client_id', 'teamLead:id,name,email'])->toArray();
    }

    public function show(Client $client): array
    {
        return $client->load(['users:id,name,email', 'adSources:id,name,platform,client_id', 'teamLead:id,name,email'])->toArray();
    }

    public function update(Request $request, Client $client): array
    {
        $data = $request->validate([
            // Базові поля
            'name' => 'sometimes|required|string|max:255|min:1',
            'link' => 'nullable|string|max:500',
            'unit' => 'sometimes|required|integer|in:1,2,3',
            'niche' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            
            // Project Card - Етап 1
            'ad_networks' => 'nullable|array',
            'ad_networks.*' => 'string|in:google,tiktok,facebook,x',
            'status' => 'sometimes|required|string|max:100',
            'start_date' => 'sometimes|required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'creative_sponsor' => 'nullable|in:за_наш_рахунок,за_їх_рахунок,комбіновано',
            'direction' => 'sometimes|required|in:leads,purchases',
            'sub_niche' => 'nullable|string|max:100',
            'scaling' => 'nullable|array',
            'scaling.enabled' => 'nullable|boolean',
            'scaling.metric' => 'nullable|required_if:scaling.enabled,true|string|in:CPA,ROAS,Share',
            'scaling.currency' => 'nullable|required_if:scaling.enabled,true|string|in:UAH,USD,EUR',
            'scaling.kpi_value' => 'nullable|required_if:scaling.enabled,true|numeric|min:0',
            'project_status' => 'nullable|in:paid,free',
            'ad_account_identifiers' => 'nullable|array',
            'ad_account_identifiers.*' => 'string',
            
            // Етап 2
            'team_lead_id' => 'nullable|integer|exists:users,id',
            'ppc_team_service' => 'nullable|string|max:255',
            'user_ids' => 'nullable|array',
            'user_ids.*' => 'integer|exists:users,id',
            'ad_source_ids' => 'nullable|array',
            'ad_source_ids.*' => 'integer|exists:ad_sources,id',
        ]);

        // Перевіряємо та очищаємо name
        if (isset($data['name'])) {
            $data['name'] = trim($data['name']);
            if (empty($data['name'])) {
                return response()->json(['error' => 'Поле "name" не може бути порожнім'], 422);
            }
        }

        // Конвертуємо порожні масиви в null
        if (isset($data['ad_networks']) && empty($data['ad_networks'])) {
            $data['ad_networks'] = null;
        }
        if (isset($data['ad_account_identifiers']) && empty($data['ad_account_identifiers'])) {
            $data['ad_account_identifiers'] = null;
        }
        if (isset($data['scaling']) && empty($data['scaling'])) {
            $data['scaling'] = null;
        }

        $adSourceIds = $data['ad_source_ids'] ?? null;
        $userIds = $data['user_ids'] ?? null;
        unset($data['ad_source_ids'], $data['user_ids']);

        $client->update($data);
        if ($userIds !== null && !empty($userIds)) {
            $client->users()->sync($userIds);
        }
        if ($adSourceIds !== null && !empty($adSourceIds)) {
            $this->syncAdSources($client, $adSourceIds);
        }

        return $client->fresh()->load(['users:id,name,email', 'adSources:id,name,platform,client_id', 'teamLead:id,name,email'])->toArray();
    }

    public function destroy(Client $client): array
    {
        $client->delete();
        return ['success' => true];
    }

    private function syncAdSources(Client $client, ?array $ids): void
    {
        if ($ids === null) {
            return;
        }
        AdSource::query()->where('client_id', $client->id)->update(['client_id' => null]);
        if (!empty($ids)) {
            AdSource::query()->whereIn('id', $ids)->update(['client_id' => $client->id]);
        }
    }

    /**
     * Генерує унікальний Project ID на основі назви проєкту
     * Формат: PRJ-YYYY-XXX (наприклад, PRJ-2026-001)
     */
    private function generateProjectId(string $projectName): string
    {
        $year = date('Y');
        $prefix = 'PRJ';
        
        // Отримуємо останній номер для поточного року
        $lastClient = Client::query()
            ->where('project_id', 'like', "{$prefix}-{$year}-%")
            ->orderBy('project_id', 'desc')
            ->first();
        
        $nextNumber = 1;
        if ($lastClient && $lastClient->project_id) {
            $parts = explode('-', $lastClient->project_id);
            if (count($parts) === 3 && $parts[0] === $prefix && $parts[1] === $year) {
                $nextNumber = (int) $parts[2] + 1;
            }
        }
        
        return sprintf('%s-%s-%03d', $prefix, $year, $nextNumber);
    }

    /**
     * Отримати список ніш за напрямком
     */
    public function getNiches(Request $request): array
    {
        $direction = $request->validate(['direction' => 'required|in:leads,purchases'])['direction'];
        
        // Базові ніші за напрямком (потім можна винести в конфіг або БД)
        $niches = match ($direction) {
            'purchases' => [
                'e-commerce',
                'retail',
                'marketplace',
            ],
            'leads' => [
                'інфобіз',
                'освіта',
                'healthcare',
                'finance',
                'real_estate',
            ],
            default => [],
        };

        return ['data' => $niches];
    }

    /**
     * Отримати список підніш за нішею
     */
    public function getSubNiches(Request $request): array
    {
        $niche = $request->validate(['niche' => 'required|string'])['niche'];
        
        // Базові підніші за нішею (потім можна винести в конфіг або БД)
        $subNiches = match ($niche) {
            'e-commerce' => [
                'зоотовари',
                'техніка',
                'одяг',
                'косметика',
                'продукти',
                'меблі',
            ],
            'інфобіз' => [
                'освіта',
                'бізнес',
                'здоров\'я',
                'фінанси',
            ],
            default => [],
        };

        return ['data' => $subNiches];
    }

    /**
     * Отримати список статусів проєктів
     */
    public function getStatuses(): array
    {
        // Базові статуси (потім можна отримувати з Analytics PPC Department)
        $statuses = [
            'до_початку',
            'до_кінця',
            'активний',
            'архів',
        ];

        return ['data' => $statuses];
    }
}
