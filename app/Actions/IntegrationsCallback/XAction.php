<?php

namespace App\Actions\IntegrationsCallback;

use App\Classes\Enums\XEventName;
use App\Models\TrackingUser;
use App\Models\XPixel;
use App\Services\Conversions\XConversionsService;
use Illuminate\Http\Request;

class XAction extends IntegrationAction
{
    public function __construct(private readonly XConversionsService $service, private readonly Request $request)
    {
    }

    public function event5(XPixel $account, TrackingUser $user): ?array
    {
        $event = match ($this->request->query('event')) {
            'new' => XEventName::START_TRIAL,
            'onboarding' => XEventName::CONTENT_VIEW,
            'purchase' => XEventName::PURCHASE,
            'active' => XEventName::CUSTOM,
            default => null
        };

        if (!$event) {
            return null;
        }

        $data = $this->service->getEventData($user, $event);

        if ($value = $this->request->query('value')) {
            $data += [
                'value' => (float)$value,
                'currency' => 'USD'
            ];
        }

        return $data;
    }
}
