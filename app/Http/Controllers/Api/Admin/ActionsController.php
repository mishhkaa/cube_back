<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Artisan;
use Illuminate\Http\JsonResponse;
use Illuminate\Queue\Console\RestartCommand;
use Illuminate\Queue\Console\RetryCommand;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class ActionsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        if (!$action = $this->request->query('action')) {
            abort(400, 'action is required');
        }

        match ($action) {
            'queue-restart' => Artisan::call(RestartCommand::class),
            'queue-retry-all' => Artisan::call(RetryCommand::class, ['id' => 'all']),
            'frontend-build' => $this->buildFrontend(),
            'delete-cache-scripts' => $this->deleteCachedScripts(),
            'optimize' => $this->runCommand('php artisan optimize:simple-clear && php artisan optimize'),
            'optimize-clear' => $this->runCommand('php artisan optimize:simple-clear'),
            default => null
        };

        return $this->response();
    }

    protected function deleteCachedScripts(): bool
    {
        return File::delete(File::files(public_path("partners/js/")));
    }

    protected function runCommand(string|array $command): void
    {
        Process::path(base_path())
            ->command(
                implode(' && ', [
                    'export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
                    "$command 2>&1"
                ])
            )
            ->forever()
            ->run();
    }

    protected function buildFrontend(): JsonResponse|bool
    {
        if (!$frontendPath = config('app.frontend-path')) {
            return $this->response("APP_FRONTEND_PATH not set", false);
        }

        $process = Process::path($frontendPath)
            ->command(
                implode(' && ', [
                    'export PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin',
                    'npm install',
                    'npm run build 2>&1'
                ])
            )
            ->run();

        return $process->successful();
    }
}
