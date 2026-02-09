<?php

namespace App\Jobs;

use App\Facades\PipeDrive;
use App\Models\DataJob;
use App\Models\Enums\JobStatus;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class FacebookLeadAdsJob implements ShouldQueue
{
    use Queueable;

    public function __construct(private array $data, private DataJob $dataJob)
    {
        $this->data['last_utm_source'] = 'facebook';
        $this->data['last_utm_medium'] = 'cpc';
        $this->onQueue('webhooks');
    }

    public function handle(): void
    {
        $this->dataJob->setProcessing();

        if ( ! empty($this->data['budget_value'])) {
            $this->data['budget'] = match (true) {
                str_contains($this->data['budget_value'], '2000-') => 430,
                str_contains($this->data['budget_value'], '5000-') => 431,
                str_contains($this->data['budget_value'], '2000') => 429,
                str_contains($this->data['budget_value'], '10000') => 432,
                default => 468
            };
        }

        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as $utm) {
            if (!empty($this->data['last_' . $utm])) {
                $this->data[$utm] = $this->data['last_' . $utm];
            }
        }

        if ( ! empty($this->data['chanel'])) {
            $this->data['chanel'] = match (strtolower($this->data['chanel'])) {
                'whatsapp' => 53,
                'telegram' => 52,
                'viber' => 58,
                default => 54
            };
        }


        $this->data['phone'] = preg_replace('/\D/', '', $this->data['phone']);

        if (empty($this->data['name'])) {
            $this->data['name'] = $this->data['phone'];
        }
        $this->data['title']       = $this->data['project'] ?: $this->data['phone'];
        $this->data['entry_point'] = 386;
        $this->data['source_lead'] = 369;
        $this->data['campaign_type'] = 569;

        if ( ! $this->data['person_id'] = PipeDrive::getAddUpdatePerson($this->data, false, false)) {
            Log::info('ProcessFacebookLeadAdsJob: no person_id');
            $this->dataJob->setDone();

            return;
        }

        $deals = PipeDrive::getDealsByPersonId($this->data['person_id']);

        if (empty($deals)) {
            $dealId = PipeDrive::addUpdateDeal($this->data)['id'] ?? null;
            if (!empty($this->data['comment']) && $dealId){
                PipeDrive::addNote($this->data['comment'], $dealId);
            }
        } else {
            foreach ($deals as $deal) {
                $data = [
                    'subject'   => 'Заявка у Facebook',
                    'note'      => 'Контакт повторно залишив заявку через миттєві форми Facebook',
                    'deal_id'   => $deal['id'],
                    'person_id' => $this->data['person_id'],
                    'type'      => 'task',
                    'done'      => false,
                ];
                PipeDrive::addActivity($data);
            }
        }

        $this->dataJob->setDone();
    }

    public function failed(Throwable $exception): void
    {
        $this->dataJob->update([
            'status' => JobStatus::ERROR,
            'message' => $exception->getMessage()
        ]);
    }
}
