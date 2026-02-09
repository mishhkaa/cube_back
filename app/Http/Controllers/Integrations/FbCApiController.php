<?php

namespace App\Http\Controllers\Integrations;

use App\Actions\FbCApiSeverEventsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\FbCApiSiteEventRequest;
use App\Models\FacebookPixel;
use App\Services\Conversions\FacebookConversionsService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class FbCApiController extends Controller
{
    public function __construct(Request $request, protected FacebookConversionsService $fbCapi)
    {
        parent::__construct($request);
    }

    public function siteEvent(FbCApiSiteEventRequest $request): array
    {
        $data = $request->validated();
        $id = $this->fbCapi->handleClientEvent(FacebookPixel::cache($data['partner_id']), $data);
        return ['id' => $id];
    }

    public function jsRender($id): Response
    {
        $content = $this->fbCapi->getContentJS($id);
        return (new Response($content, 200))
            ->header('Content-Type', 'application/javascript; charset=utf-8');
    }

    public function crmEvent(FacebookPixel $account, int|string|null $leadId = null, $eventName = null, $nameCrm = 'CRM'): void
    {
        $suffixMethod = $this->request->query('group', '');
        $method = "crmEvent{$account->id}{$suffixMethod}";

        if (method_exists($this, $method)) {
            $this->{$method}($account, $leadId, $eventName, $nameCrm);
            return;
        }

        $leadId = $leadId ?: (int)$this->request->get('lead_id');
        $eventName = $eventName ?: $this->request->get('event_name');

        if (!$leadId || !$eventName) {
            return;
        }

        $data = $this->fbCapi->getCrmEvent($leadId, $eventName, $nameCrm);

        $this->fbCapi->dispatchEvent($data, $account);
    }

    public function serverEvent(FbCApiSeverEventsAction $eventsAction, FacebookPixel $account, int|string|null $externalId = null): void
    {
        $suffixMethod = $this->request->query('group', '');
        $method = "event{$account->id}{$suffixMethod}";
        
        // Для event130 дозволяємо створювати користувача, якщо його немає (для події Install)
        $shouldCreateIfNotExists = $method === 'event130';
        
        $data = $externalId 
            ? ($shouldCreateIfNotExists 
                ? $this->fbCapi->getEventDataOrCreate($externalId) 
                : $this->fbCapi->getEventData($externalId))
            : [];
            
        if ($externalId && !$data && !$shouldCreateIfNotExists) {
            return;
        }

        if (method_exists($eventsAction, $method)) {
            // Для event130 перетворюємо null на порожній масив
            if ($shouldCreateIfNotExists && $data === null) {
                $data = [];
            }
            if ($eventData = $eventsAction->{$method}($account, $data, $externalId)) {
                $this->fbCapi->dispatchEvent($eventData, $account);
            }
        } else {
            // Логуємо тільки якщо є externalId або це POST запит (не просто GET без параметрів)
            if ($externalId || $this->request->method() === 'POST' || !empty($this->request->all())) {
                Log::info("Facebook CApi: method '{$method}' for id {$account->id} not set", [
                    'external_id' => $externalId,
                    'method' => $this->request->method(),
                    'referer' => $this->request->headers->get('referer'),
                ]);
            }
        }
    }
}
