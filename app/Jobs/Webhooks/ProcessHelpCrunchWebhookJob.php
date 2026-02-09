<?php

namespace App\Jobs\Webhooks;

use App\Facades\PipeDrive;
use App\Models\DataJob;
use App\Models\Enums\JobStatus;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Throwable;

class ProcessHelpCrunchWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(protected array $data, protected DataJob $dataJob)
    {
        $this->onQueue('webhooks');
    }

    /**
     * @throws Exception
     */
    public function handle(): void
    {
        $this->dataJob->setProcessing();

        if ($this->get('event') != 'chat.status_updated' || $this->get('eventData.status') != 'pending'){
            $this->dataJob->setDone();
            return;
        }

        $customerId = $this->get('eventData.customer.id');

        if (!$customerId || !($customer = $this->getCustomer($customerId))){
            $this->dataJob->setDone();
            return;
        }

        $data = $customer['customData'] ?? [];
        $data += [
            'name' => $customer['name'],
            'email' => $customer['email'],
            'phone' => $customer['phone'],
            'title' => $customer['name'] ?: 'Заявка з чату',
            'entry_point' => 387,
            'type_of_appeal' => 408,
            'stage_id' => 118
        ];

        $data['person_id'] = PipeDrive::getAddUpdatePerson($data, true);

        $data['deal_id'] = PipeDrive::addUpdateDeal($data)['id'] ?? null;

        $nodeText = 'https://mediangrp.helpcrunch.com/v2/chats/' . $this->get('eventData.chatId');
        PipeDrive::addNote($nodeText, $data['deal_id']);

        $this->dataJob->setDone();
    }

    protected function get($key, $default = null)
    {
        return data_get($this->data, $key, $default);
    }

    /**
     * @throws Exception
     */
    protected function getCustomer($id)
    {
        $link = 'https://api.helpcrunch.com/v1/customers/' . $id;

        if (!$token = config('services.helpcrunch.token')){
            throw new Exception("Helpcrunch token not set");
        }

        return Http::withToken($token)
            ->asJson()
            ->get($link)
            ->json();
    }


    public function failed(Throwable $exception): void
    {
        $this->dataJob->update([
            'status' => JobStatus::ERROR,
            'message' => $exception->getMessage()
        ]);
    }
}
