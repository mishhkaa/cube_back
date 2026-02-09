<?php

namespace App\Http\Controllers\Api\Integrations;

use App\Http\Controllers\Controller;
use App\Models\ScriptBundle;
use App\Services\IntegrationsService;
use App\Services\ScriptBundlesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class ScriptBundleController extends Controller
{
    public function index(): LengthAwarePaginator
    {
        return ScriptBundle::orderBy('id', 'desc')->paginate(20);
    }

    public function integrationsAccounts(): array
    {
        return collect(IntegrationsService::getIntegrationsModels())
            ->mapWithKeys(function ($class){
                return [Str::camelClass($class) => $class::get(['id', 'name'])];
            })
            ->toArray();
    }

    public function jsContent(): array
    {
        return ['data' => ScriptBundlesService::getIndexJSContent()];
    }

    public function store(): array
    {
        $payload = $this->request->input('data', $this->request->post());
        return tap(ScriptBundle::create($payload), function (ScriptBundle $script_bundle) use ($payload) {
            $script_bundle->setIntegrations($payload['integrations'] ?? null);
        })->toArray();
    }

    public function update(ScriptBundle $script_bundle): array
    {
        ScriptBundlesService::deleteJsCacheFile($script_bundle->id);
        $payload = $this->request->input('data', $this->request->post());
        return tap($script_bundle, function (ScriptBundle $script_bundle) use ($payload) {
            $script_bundle->update($payload);
            $script_bundle->setIntegrations($payload['integrations'] ?? null);
        })->toArray();
    }

    public function destroy(ScriptBundle $script_bundle): JsonResponse
    {
        ScriptBundlesService::deleteJsCacheFile($script_bundle->id);
        $script_bundle->delete();
        return $this->response();
    }
}
