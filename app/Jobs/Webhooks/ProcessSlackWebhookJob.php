<?php

namespace App\Jobs\Webhooks;

use App\Models\DataJob;
use App\Models\Enums\JobStatus;
use App\Notifications\UpWorkJob;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Notification;
use Throwable;

class ProcessSlackWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(protected array $data, protected DataJob $dataJob)
    {
        $this->onQueue('webhooks');
    }

    public function handle(): void
    {
        $type = $this->get('type');

        match ($type){
            'block_actions' => $this->blockActions(),
            default => null
        };

        $this->dataJob->setDone();
    }

    protected function get($key, $default = null)
    {
        return data_get($this->data, $key, $default);
    }

    protected function blockActions(): void
    {
        foreach ($this->get('actions', []) as $action){
            $actionId = explode(':',$action['action_id'])[0];
            match ($actionId){
                'upwork_to_work' => $this->upWorkToWorkAction($action),
                default => null
            };
        }
    }

    protected function upWorkToWorkAction(array $action): void
    {
        $message = $this->get('message.blocks.0.text.text');
        $chat = $this->get('channel.id');
        $ts = $this->get('message.ts');
        $userName = $this->get('user.username');

        $subAction = explode(':',$action['action_id'])[1] ?? null;

        if (!$message || !$chat || !$ts || !$subAction){
            return;
        }

        $nextAction = match ($subAction){
            'start' => 'approve',
            'approve' => 'approved',
            'reject' => 'rejected',
            default => null
        };

        if (!$nextAction){
            return;
        }

        Notification::route('slack', $chat)
            ->notifyNow(new UpWorkJob($message, $nextAction, $userName, $ts));
    }

    public function failed(Throwable $exception): void
    {
        $this->dataJob->update([
            'status' => JobStatus::ERROR,
            'message' => $exception->getMessage()
        ]);
    }
}
