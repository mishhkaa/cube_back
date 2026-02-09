<?php

namespace App\Actions\IntegrationsCallback;

use App\Models\TikTokPixel;
use App\Models\TrackingUser;
use App\Services\Conversions\TikTokEventsService;
use Illuminate\Http\Request;

class TikTokAction extends IntegrationAction
{
    public static function optionWebDispatchData(): array
    {
        return ['web'];
    }

    public function __construct(
        private readonly TikTokEventsService $service,
        private readonly Request $request
    )
    {
    }

    public function event4(TikTokPixel $account, TrackingUser $user): ?array
    {
        if (!$event = match ($this->request->query('event')) {
            'lead' => 'Lead',
            'send' => 'CompleteRegistration',
            'purchase' => 'Purchase',
            default => null
        }) {
            return null;
        }

        $eventData = $this->service->getEventData($user, $event, 'https://shop.azi.com.ua/');

        if ($amount = $this->request->query('value')) {
            $eventData['properties'] = [
                'value' => (float)$amount,
                'currency' => 'UAH'
            ];
        }

        return $eventData;
    }
}
