<?php

namespace App\Jobs\Webhooks;

use App\Facades\PipeDrive;
use App\Models\DataJob;
use App\Models\Enums\JobStatus;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessRingostatWebhookJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{
     *     phone: string,
     *     call_date: string,
     *     call_type: 'in'|'out',
     *     user_call: string,
     *     call_status: string,
     *     call_duration: string<int>,
     *     call_recording: string,
     *     ga?: string,
     *     ip?: string,
     *     url?: string,
     *     utm_term?: string,
     *     session_id?: string,
     *     utm_source?: string,
     *     utm_content?: string,
     *     utm_campaign: string,
     *     last_utm_term: string,
     *     last_utm_medium: string,
     *     last_utm_source: string,
     *     last_utm_content: string,
     *     last_utm_campaign: string,
     *     substitution_type: string,
     *     fb_partners_user_id: string,
     *     person_id?: int
     * }  $data
     * @param  DataJob  $dataJob
     */
    public function __construct(protected array $data, protected DataJob $dataJob)
    {
        $this->onQueue('webhooks');
    }

    public function handle(): void
    {
        $this->dataJob->setProcessing();

        if (empty($this->data['phone'])) {
            Log::info('ProcessRingostatWebhookJob: no phone');
            $this->dataJob->setDone();
            return;
        }
        $this->data['campaign_type'] = 568;
        $this->data['phone'] = preg_replace('/\D/', '', $this->data['phone']);
        $this->data['name'] = $this->data['phone'];
        if (!$this->data['person_id'] = PipeDrive::getAddUpdatePerson($this->data, true, false)) {
            Log::info('ProcessRingostatWebhookJob: no person_id');
            $this->dataJob->setDone();
            return;
        }

        if ($this->data['call_type'] === 'in') {
            $deals = PipeDrive::getDealsByPersonId($this->data['person_id']);
//            $deals = array_filter($deals, static fn ($deal) => $deal['pipeline_id'] === 15);

            if (empty($deals)) {
                $dealId = $this->createDeal();
                $this->createCallLog($dealId);
            }else{
                $oneDeal = count($deals) > 1 ? null : $deals[0]['id'];
                $this->createCallLog($oneDeal);
                PipeDrive::addActivity([
                    'subject' => 'Вхідний дзвінок',
                    'note' => 'Контакт повторно подзвонив',
                    'person_id' => $this->data['person_id'],
                    'type' => 'vkhdniy_dzvnok1',
                    'done' => false,
                ] + ($oneDeal ? ['deal_id' => $oneDeal] : []));
            }
        } elseif($this->data['call_type'] === 'out') {
            $deals = PipeDrive::getDealsByPersonId($this->data['person_id']);
            if ($dealId = $this->getLastActivityDeal($deals)){
                $this->createCallLog($dealId);
            }
        }

        $this->dataJob->setDone();
    }

    protected function getLastActivityDeal(array $deals): ?int
    {
        if (empty($deals)) {
            return null;
        }

        if (count($deals) === 1){
            return $deals[0]['id'];
        }

        $fields = ['update_time', 'next_activity_date', 'last_activity_date'];
        $lastActiveDate = null;
        $dealId = 0;
        foreach ($deals as $deal) {
            if (!empty($dealId)){
                $dealId = $deal['id'];
            }

            if (!$lastActiveDate){
                $lastActiveDate = Carbon::parse($deal['update_time']);
            }

            foreach ($fields as $field) {
                if (empty($deal[$field])){
                    continue;
                }
                $date = Carbon::parse($deal[$field]);
                if ($date->greaterThan($lastActiveDate)){
                    $lastActiveDate = $date;
                    $dealId = $deal['id'];
                }
            }
        }

        return $dealId;
    }

    protected function getNoteForCallLog(): string
    {
        if (in_array($this->data['call_status'], ['ANSWERED', 'PROPER', 'REPEATED'])) {
            return '<a href="'.$this->data['call_recording'].'">Запис розмови</a>';
        }

        if ($this->data['call_type'] === 'in') {
            return 'Було пропущено дзвінок. Передзвоніть на цей номер. Пропущений дзвінок - кращий подарунок конкуренту!';
        }

        if ($this->data['call_status'] === 'BUSY') {
            return 'Статус дзвінка: Зайнято';
        }

        return 'Статус дзвінка: Не відповідає';
    }

    protected function createCallLog(?int $dealId): void
    {
        $callStatusSuccess = $this->data['call_status'] === 'ANSWERED';
        $outcome = match ($this->data['call_status']) {
            'REPEATED', 'PROPER',
            'ANSWERED' => 'connected',
            'BUSY' => 'busy',
            default => 'no_answer'
        };
        $callType = $this->data['call_type'] === 'in' ? 'Вхідний' : 'Вихідний';

        $startDateCall = Carbon::parse($this->data['call_date'])->setTimezone('UTC');

        $callLog = [
            'person_id' => $this->data['person_id'],
            'to_phone_number' => '+'.$this->data['phone'],
            'outcome' => $outcome,
            'subject' => "$callType дзвінок",
//            'user_id' => (int)$this->data['user_call'] === 340944 ? 16037823 : 21459945,
            'user_id' => 23062458,
            'start_time' => $startDateCall->format('Y-m-d H:i:s'),
            'end_time' => $callStatusSuccess
                ? $startDateCall->addSeconds((int)$this->data['call_duration'])->format('Y-m-d H:i:s')
                : $startDateCall->format('Y-m-d H:i:s'),
            'note' => $this->getNoteForCallLog(),
        ];
        if ($dealId) {
            $callLog['deal_id'] = $dealId;
        }

        $id = PipeDrive::sendRequest('callLogs', $callLog, 'POST')['id'] ?? null;

        if (!empty($this->data['call_recording']) && $id) {
            PipeDrive::addCallLogRecording($id, $this->data['call_recording']);
        }
    }

    protected function createDeal(): ?int
    {
        $isCallback = !empty($this->data['substitution_type']) && $this->data['substitution_type'] === 'callback';
        $this->data += [
            'entry_point' => $isCallback ? 388 : 446,
            'type_of_appeal' => $isCallback ? 409 : 443,
            'title' => $this->data['phone']
        ];
        return PipeDrive::addUpdateDeal($this->data)['id'] ?? null;
    }

    public function failed(Throwable $exception): void
    {
        $this->dataJob->update([
            'status' => JobStatus::ERROR,
            'message' => $exception->getMessage()
        ]);
    }
}
