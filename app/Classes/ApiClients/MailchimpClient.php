<?php

namespace App\Classes\ApiClients;

use Exception;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MailchimpClient
{
    private PendingRequest $client;

    private string $listId;

    public function __construct(?string $listId = null)
    {
        if (!$token = config('services.mailchimp.apiKey')) {
            throw new RuntimeException('MAILCHIMP_API_KEY not set in env file');
        }

        if (count($particles = explode('-', $token)) !== 2) {
            throw new RuntimeException('Invalid api key');
        }

        $this->client = Http::withToken($token)
            ->baseUrl("https://$particles[1].api.mailchimp.com/3.0/")
            ->acceptJson()->asJson();


        $this->listId = $listId ?: config('services.mailchimp.listId', '');
    }

    public function setListID(string $listId): static
    {
        return new static($listId);
    }

    public function setListMember(string $email, array $options = [], array $fields = [], array $interests = [])
    {
        $url = 'lists/{list_id}/members/'.md5($email);

        if ($interests && array_is_list($interests)) {
            foreach ($interests as $key => $interest) {
                unset($interests[$key]);
                $interests[$interest] = true;
            }
        }

        $data = array_filter([
            'email_address' => $email,
            'status_if_new' => 'subscribed',
            'status' => $options['status'] ?? null,
            'language' => $options['language'] ?? null,
            'ip_signup' => $options['ip'] ?? null,
            'merge_fields' => $fields,
            'interests' => $interests
        ]);

        return $this->send($url, $data, 'put');
    }

    public function getAllInterestsCategories(): array
    {
        $url = 'lists/{list_id}/interest-categories';
        $categoriesObject = $this->send($url);

        $data = [];

        foreach ($categoriesObject['categories'] ?? [] as $item){
            $interestsObject = $this->send($url . '/' . $item['id'] . '/interests');
            $data[] = [
                'id' => $item['id'],
                'name' => $item['title'],
                'interests' => collect($interestsObject['interests'] ?? [])
                    ->map(fn($v) => ['id' => $v['id'], 'name' => $v['name']])
                    ->toArray()
            ];
        }
        return $data;
    }

    public function createListMemberEvent(string $email, string $eventName, array $properties = [])
    {
        $url = '/lists/{list_id}/members/' . md5($email) . '/events';
        $data = [
            'name' => $eventName,
            'properties' => $properties,
        ];

        return $this->send($url, $data);
    }

    protected function getListId()
    {
        if (!$this->listId) {
            throw new RuntimeException('MAILCHIMP_DEFAULT_LIST_ID not set in env file');
        }

        return $this->listId;
    }

    /**
     * @throws Exception
     */
    public function send(string $url, array $data = [], string $method = 'get')
    {
        $url = str_replace('{list_id}', $this->getListId(), $url);

        if ($data && strtolower($method) === 'get') {
            $method = 'post';
        }

        return $this->client->send($method, $url, ['json' => $data])->json();
    }
}
