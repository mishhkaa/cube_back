<?php

namespace App\Jobs\Webhooks;

use App\Facades\PipeDrive;
use App\Models\DataJob;
use App\Models\Enums\JobStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessAdsQuizWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private array $data;

    public function __construct(array $data, protected DataJob $dataJob)
    {
        $this->formattingData($data);
        $this->onQueue('webhooks');
    }

    public function handle(): void
    {
        if (empty($this->data['name'])) {
            $this->data['name'] = $this->data['phone'];
        }
        $this->data['title'] = $this->data['project'] ?: $this->data['phone'];

        if ( ! $this->data['person_id'] = PipeDrive::getAddUpdatePerson($this->data, false, false)) {
            Log::info('ProcessAdsQuizWebhookJob: no person_id');
            $this->dataJob->setDone();

            return;
        }

        $dealId = PipeDrive::addUpdateDeal($this->data)['id'] ?? null;
        if ( ! empty($this->data['comment']) && $dealId) {
            PipeDrive::addNote($this->data['comment'], $dealId);
        }

        $this->dataJob->setDone();
    }

    private function formattingData(array $data): void
    {
        $comment   = '';
        $budget = null;
        foreach ($data['simpleData']['answers'] ?? [] as ['answer' => $answer, 'question' => $question]) {
            if (str_contains($question, "рекламний бюджет?")) {
                $budget = match (true) {
                    str_contains($answer, '10 000') => 432,
                    str_contains($answer, '5 000$ -') => 431,
                    str_contains($answer, '2 000$ -') => 430,
                    str_contains($answer, '2 000') => 429,
                    default => 468
                };
                continue;
            }
            $comment .= "<b> $question </b>: $answer <br>";
        }

        $this->data = [
            'fb_partners_user_id' => $data['UUID'] ?? null,
            'gclid'               => $data['gclid'] ?? null,
            'utm_source'          => $data['utm_source'] ?? null,
            'utm_medium'          => $data['utm_medium'] ?? null,
            'utm_campaign'        => $data['utm_campaign'] ?? null,
            'utm_content'         => $data['utm_content'] ?? null,
            'utm_term'            => $data['utm_term'] ?? null,
            'last_utm_source'     => $data['utm_source'] ?? null,
            'last_utm_medium'     => $data['utm_medium'] ?? null,
            'last_utm_campaign'   => $data['utm_campaign'] ?? null,
            'last_utm_content'    => $data['utm_content'] ?? null,
            'last_utm_term'       => $data['utm_term'] ?? null,
            'ip'                  => $data['ip'] ?? null,
            'url'                 => $data['quiz']['domain'] ?? null,
            'entry_point'         => 386,
            'campaign_type'       => 568,
            'name'                => $data['simpleData']['contacts']['surname']['value'] ?? null,
            'email'               => $data['simpleData']['contacts']['email']['value'] ?? null,
            'phone'               => preg_replace('/\D/', '', $data['simpleData']['contacts']['phone']['value'] ?? ''),
            'project'             => $data['simpleData']['contacts']['name']['value'] ?? null,
            'comment'             => $comment,
            'budget'              => $budget,
        ];
    }

    public function failed(Throwable $exception): void
    {
        $this->dataJob->update([
            'status'  => JobStatus::ERROR,
            'message' => $exception->getMessage(),
        ]);
    }
}
