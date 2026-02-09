<?php

namespace App\Jobs\EventsSenders;

use App\Facades\X;
use App\Models\DataJob;
use App\Models\Enums\JobStatus;
use App\Models\XPixel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class XConversionSendJob implements ShouldQueue
{
    use Queueable;

    public function __construct(protected DataJob $dataJob, protected int $accountId)
    {
        $this->onQueue('conversion_sender');
    }

    public function handle(): void
    {
        $this->dataJob->setProcessing();

        $pixel = XPixel::cache($this->accountId);

        [$status, $message, $response] = match (true) {
            !$pixel => [JobStatus::ERROR, 'account not found', null],
            !$pixel->active => [JobStatus::ERROR, 'account not active', null],
            !$pixel->pixel_id || !$pixel->access_token => [JobStatus::ERROR, 'Account not available', null],
            !$pixel->user || !$pixel->user->x_token_data => [JobStatus::ERROR, 'User not access to x', null],
            default => $this->sendConversion($pixel->pixel_id, $pixel->user->x_token_data)
        };

        $this->dataJob->update([
            'response' => $response,
            'message' => $message,
            'status' => $status
        ]);
    }

    private function sendConversion(string $pixel, string $tokenData): array
    {
        $res = X::setTokenAndSecret(...explode(':', $tokenData))
            ->sendConversion($pixel, $this->dataJob->payload);

        [$status, $message] = match (true){
            $res->successful() => [JobStatus::DONE, null],
            $res->failed() => [JobStatus::ERROR, $res->json('message')],
            default => [JobStatus::WARNING, 'status code: ' . $res->status()]
        };

        return [$status, $message, $res->json()];
    }

    public function failed(Throwable $exception): void
    {
        $this->dataJob->update([
            'status' => JobStatus::ERROR,
            'message' => $exception->getMessage(),
        ]);
    }
}
