<?php

namespace App\Jobs\EventsSenders;

use App\Models\DataJob;
use App\Models\Enums\JobStatus;
use App\Models\Notice;
use App\Models\TikTokPixel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class TikTokEventSenderJob implements ShouldQueue
{
    use Queueable;

    public function __construct(protected DataJob $dataJob, protected int $accountId, protected string $eventSource)
    {
        $this->onQueue('conversion_sender');
    }

    public function handle(): void
    {
        $this->dataJob->setProcessing();

        $pixel = TikTokPixel::cache($this->accountId);

        [$status, $message, $response] = match (true) {
            !$pixel => [JobStatus::ERROR, 'account not found', null],
            !$pixel->active => [JobStatus::ERROR, 'account not active', null],
            !$pixel->pixel_id || !$pixel->access_token => [JobStatus::ERROR, 'Account not available', null],
            default => $this->sendEvent($pixel, $this->dataJob->payload)
        };

        $this->dataJob->update([
            'response' => $response,
            'message' => $message,
            'status' => $status
        ]);
    }

    private function sendEvent(TikTokPixel $pixel, array $eventData): array
    {
        $url = 'https://business-api.tiktok.com/open_api/v1.3/event/track/';

        $body = [
            'event_source' => $this->eventSource,
            'event_source_id' => $pixel->pixel_id,
            'data' => [$eventData]
        ];

        $res = Http::asJson()
            ->withHeader('Access-Token', $pixel->access_token)
            ->withOptions(['http_errors' => false])
            ->post($url, $body);

        if ($res->failed() || $res->json('code') === 40001) {
            Log::info('TikTok event send error', $res->json() ?? []);
            $this->disableAccount($pixel);
        }

        [$status, $message] = match (true){
            $res->successful() => [JobStatus::DONE, null],
            $res->failed() => [JobStatus::ERROR, $res->json('message')],
            default => [JobStatus::WARNING, 'status code: ' . $res->status()]
        };

        return [$status, $message, $res->json()];
    }

    protected function disableAccount(TikTokPixel $pixel): void
    {
        $pixel->disable();
        Notice::query()->create([
            'name' => 'TikTok аккаунт вимкнено',
            'type' => Notice::DASHBOARD,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        $this->dataJob->update([
            'status' => JobStatus::ERROR,
            'message' => $exception->getMessage(),
        ]);
    }
}
