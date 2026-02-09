<?php

namespace App\Jobs\EventsSenders;

use App\Models\DataJob;
use App\Models\Enums\JobStatus;
use App\Models\FacebookPixel;
use App\Models\Notice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Throwable;

class FbEventSenderJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(protected DataJob $dataJob, protected int $accountId)
    {
        $this->onQueue('conversion_sender');
    }

    public function handle(): void
    {
        $this->dataJob->setProcessing();

        $account = FacebookPixel::cache($this->accountId);

        [$status, $message, $response] = match(true){
            !$account => [JobStatus::ERROR, 'account not found', null],
            !$account->active => [JobStatus::ERROR, 'account not active', null],
            !$account->pixel_id || !$account->access_token => [JobStatus::ERROR, 'Account not available', null],
            default => $this->sendEvent($account, $this->dataJob->payload)
        };

        $this->dataJob->update([
            'response' => $response,
            'message' => $message,
            'status' => $status
        ]);
    }

    protected function sendEvent(FacebookPixel $account, array $eventData): array
    {
        $url = "https://graph.facebook.com/v21.0/{$account->pixel_id}/events";

        $body = [
            'access_token' => $account->access_token,
            'data' => [$eventData]
        ];
        $res = Http::asForm()->withOptions([
            'http_errors' => false
        ])->post($url, $body);

        $data = $res->json();

        if (!empty($data['error']['code']) && !empty($data['error']['error_subcode'])
            && $data['error']['code'] === 100 && $data['error']['error_subcode'] === 33) {
            $this->disableAccount($account);
        }

        [$status, $message] = match (true){
            $res->successful() => [JobStatus::DONE, null],
            $res->failed() => [JobStatus::ERROR, $res->toException()?->getMessage()],
            default => [JobStatus::WARNING, 'status code: ' . $res->status()]
        };
        return [$status, $message, $data];
    }

    protected function disableAccount(FacebookPixel $account): void
    {
        $account->update(['active' => false]);
        Notice::query()->create([
            'name' => 'Fb CApi аккаунт вимкнено',
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
