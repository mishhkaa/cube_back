<?php

namespace App\Classes\ApiClients;

use App\Collections\PipeDriveCollection;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PipeDriveClient
{

    protected array $customFieldsContact = [
        'messenger' => '6ec64280cc2aeb3df01b310f548fa7a37775d65f',
        'chanel' => '5eedb57ac48506ce16b71fec1ba96cf293d76717',
        'link_page' => 'cd957733c51216cd940ca592fade91f02a76dd6f',
        'city' => '6114144066bede75cbfe55ef0a18baa3266bcfa2',
        'ip' => '3a481ecf7a037d0ea0033729da40b31e56a9facc',
        'country' => '6114144066bede75cbfe55ef0a18baa3266bcfa2',
        'timezone' => 'dc25cf582d8263626d447068655c9710bdc871ed'
    ];
    protected array $customFieldsDeal = [
        'utm_content' => '7d6bf531097247593c811114de2511c5269f1a3c',
        'utm_medium' => '7cd9441a6f174789aa1935d5565d4c4a31da2cee',
        'utm_campaign' => 'fbae4a25687addb13002c393c12f6b59f572cc9b',
        'utm_source' => '548912079d730de9dac3b62285bd578c3c7d8369',
        'utm_term' => 'ee6faf418770cda95f88162544f778947d72c5a8',

        'last_utm_content' => '2bb9509ab03f0ca06256f5323ca16c79f39106df',
        'last_utm_medium' => '57f40515431ef1041cbed535babed1182c14c791',
        'last_utm_campaign' => '6f1c1a22830bf51f79cc409dcb7210050db0ab9c',
        'last_utm_source' => 'd2240f230fc6fd71100b0df38691cd72c3b73810',
        'last_utm_term' => '1574457b20dcb38da5cfe87f67d63d595c2a17ae',

        'url' => '25f0bbab67a342dfddbc6584ac3a628a66bd9513',
        'partner' => '23dff4ccfa17bb394c8f4f0a8e07d500067d12f9',
        'partner_id' => 'a80195e14e85634096b4c87a5cd8badb60c9b14e',
        'owner_deal' => 'bdb7f421b39877ca12b6263c222465da0682cd68',
        'website' => '1884aa5957277c1e0aea01bacd3df84b4e4af1c6',
        'source_lead' => '2b67218c35a4530fa1d5c70613f8f1fd44baf372',
        'loyalty_lead' => '28a4881534f8a3cdaf03b0b56784ca8ed38b1d6f',
        'type_of_appeal' => 'b02651705cf51729bb23384b618a7cc52228689f',
        'entry_point' => '969a6e652c5edce69b83be6f9b25e89bfa380c63',
        'lead_request' => '02b414ab0d23c8c795fe81e0a7a6897b735fb0e1', // Запит ліда
        'budget' => 'db97e6a512f2df482f3cbda60b002bfb21e5c7ea',
        'campaign_type' => '1292ac89e60eaaa34ef6e8f2b5e7fc5b27c04cc4',

        'fb_partners_user_id' => 'ffbe4b814d568dbc0bd6c81e19b3062c4923561f',
        'ga' => 'b8a91a19e62cc439261626c730d7d101270235d9',
        'gclid' => '00961d439cd1fdc613689d81fb1d06b9aff03c36',
        'session_id' => 'afcac4620fa8518dc324f4b95f22c1a84e97b42e',
        'lead_id' => '8fcf5b7113426460f1c731698e4ef55f9a19756d', // facebook lead id
    ];

    protected PendingRequest $client;

    public function __construct()
    {
        $this->client = $this->getNewClient();
    }

    protected function getNewClient(): PendingRequest
    {
        if (!$token = config('services.pipedrive.token')) {
            throw new RuntimeException("PIPEDRIVE_API_KEY not set in env file");
        }
        return Http::baseUrl(config('services.pipedrive.url'))
            ->withQueryParameters(['api_token' => $token]);
    }

    // deal_id
    public function addUpdateDeal(array $data): PipeDriveCollection
    {
        $lead = [
            'title' => $data['title'] ?? 'New deal',
            'stage_id' => $data['stage_id'] ?? 118,
            'user_id' => 23062458
        ];

        foreach (['person_id', 'org_id', 'user_id'] as $item) {
            if (!empty($data[$item])) {
                $lead[$item] = $data[$item];
            }
        }
        foreach ($this->customFieldsDeal as $k => $v) {
            if (isset($data[$k])) {
                $lead[$v] = $data[$k];
            }
        }

        $uri = 'deals/'.($data['deal_id'] ?? '');
        return $this->sendRequest($uri, $lead, !empty($data['deal_id']) ? 'PUT' : 'POST');
    }

    public function getDealsByPersonId(int $id, string $status = 'open'): array
    {
        return $this->sendRequest("persons/$id/deals", ['status' => $status])->toArray();
    }

    /**
     * @param  array{
     *     subject: string,
     *     type: 'task'|'deadline'|'meeting',
     *     owner_id: int,
     *     deal_id: int,
     *     lead_id: int,
     *     person_id: int,
     *     org_id: int,
     *     project_id: int,
     *     due_date: string,
     *     due_time: string,
     *     note: string,
     *     done: bool
     * } $params
     * @return int|null
     */
    public function addActivity(array $params): ?int
    {
        return $this->sendRequest("activities", $params, 'POST')['id'] ?? null;
    }

    public function addCallLogRecording(string $id, string $pathToAudio): bool
    {
        $filename = '';
        if (filter_var($pathToAudio, FILTER_VALIDATE_URL)) {
            $filename = basename(parse_url($pathToAudio, PHP_URL_PATH));
            Storage::put($filename, retry(3, static function () use ($pathToAudio) {
                return file_get_contents($pathToAudio);
            }, 1000));
            $pathToAudio = Storage::path($filename);
        }elseif (!file_exists($pathToAudio)) {
            return false;
        }
        $client = $this->getNewClient();
        $res = $client
            ->attach('file', Utils::tryFopen($pathToAudio, 'rb'))
            ->acceptJson()
            ->post("callLogs/$id/recordings");

        if ($filename){
            Storage::delete($filename);
        }

        if (!$res->json('success') ){
            Log::info('Pipedrive client: failed to attach call recording', [$res->json() ?: $res->getBody()->getContents()]);
            return false;
        }

        return true;
    }

    public function getDataByIp(string $ip): array
    {
        if (!$ip) {
            return [];
        }

        $data = [];
        if ($info = Http::get("https://freeipapi.com/api/json/$ip")->json()) {
            if (!empty($info['timeZone'])) {
                $data['timezone'] = $info['timeZone'];
            }
            if (!empty($info['countryName'])) {
                $data['country'] = $info['countryName'];
            }
        }
        return $data;
    }

    public function getAddUpdatePerson(array $data, bool $addDataByIp = false, bool $updateName = true): ?int
    {
        $contact = [];

        if ($addDataByIp && !empty($data['ip'])) {
            $data += $this->getDataByIp($data['ip']);
        }

        if (!empty($data['email'])) {
            $contact = $this->searchPersons($data['email'])[0] ?? [];
        }

        if (!$contact && !empty($data['phone'])) {
            $contact = $this->searchPersons($data['phone'])[0] ?? [];
        }

        if ($contact) {
            $data['person_id'] = $contact['id'];
            if (!$updateName){
                $data['name'] = $contact['name'];
            }
            $this->addUpdatePerson($data);
            return $contact['id'];
        }

        return $this->addUpdatePerson($data)['id'] ?? null;
    }

    public function getAddUpdateOrganization(array $data): ?int
    {
        if (!empty($data['project'])) {
            $data['org_id'] = $this->getOrganizationIDByName($data['project']);
            if (!$data['org_id']) {
                $data['org_id'] = $this->addUpdateOrganization($data)['id'] ?? null;
            }
        }

        return $data['org_id'] ?? null;
    }

    public function getOrganizationIDByName(string $name): ?int
    {
        $res = $this->sendRequest('organizations/search', ['term' => $name, 'fields' => 'name']);
        foreach ($res['items'] ?? [] as $item) {
            if (!empty($item['item']['name']) && $item['item']['name'] === $name) {
                return $item['item']['id'] ?? null;
            }
        }
        return null;
    }

    // org_id
    public function addUpdateOrganization(array $data): ?PipeDriveCollection
    {
        if (empty($data['project'])) {
            return null;
        }
        $org['name'] = $data['project'];
        $link = 'organizations/'.($data['org_id'] ?? '');
        return $this->sendRequest($link, $org, empty($data['org_id']) ? 'POST' : 'PUT');
    }

    // person_id
    public function addUpdatePerson($data): PipeDriveCollection
    {
        $contact = [
            'name' => $data['name'] ?? 'New person',
        ];

        foreach ($this->customFieldsContact as $k => $v) {
            if (isset($data[$k])) {
                $contact[$v] = $data[$k];
            }
        }

        foreach (['email', 'phone'] as $item) {
            if (empty($data[$item]) || is_array($data[$item])) {
                continue;
            }
            $contact[$item] = [
                [
                    'value' => $data[$item],
                    'primary' => true,
                    'label' => ""
                ]
            ];
        }

        $link = 'persons/'.($data['person_id'] ?? '');
        return $this->sendRequest($link, $contact, !empty($data['person_id']) ? 'PUT' : 'POST');
    }

    public function searchPersons($term): array
    {
        $uri = 'persons/search';
        $res = $this->sendRequest($uri, ['term' => $term])->get('items');
        return array_column($res ?: [], 'item');
    }

    public function getPerson($id): PipeDriveCollection
    {
        $uri = 'persons/'.$id;
        return $this->sendRequest($uri);
    }

    public function addNote(string $content, int $id, string $key = 'deal_id'): PipeDriveCollection
    {
        return $this->sendRequest('notes', [
            'content' => $content,
            $key => $id
        ], 'POST');
    }

    public function sendRequest(string $link, array $body = [], $method = 'GET'): PipeDriveCollection
    {
        if ($method === 'GET') {
            $query = $body;
            $body = [];
        }

        $res = $this->client
            ->send($method, trim($link, '/'), [
                'json' => $body,
                'query' => $query ?? []
            ]);

        if (!$res->json('success')) {
            Log::info("Pipedrive client: $method $link", ['send' => $body, 'response' => $res->json()]);
            return new PipeDriveCollection([], []);
        }

        $collect = new PipeDriveCollection($res->json('data', []), $res->json('related_objects', []));

        if ($next = $res->json('additional_data.pagination.next_start')) {
            usleep(500);

            $body = array_merge($body, [
                'limit' => $res->json('additional_data.pagination.limit'),
                'start' => $next
            ]);
            $collect->merge($this->sendRequest($link, $body, $method));
        }

        return $collect;
    }

    public function getClient(): PendingRequest
    {
        return clone $this->client;
    }
}
