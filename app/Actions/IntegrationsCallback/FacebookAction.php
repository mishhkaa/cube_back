<?php

namespace App\Actions\IntegrationsCallback;

use App\Models\FacebookPixel;
use App\Models\TrackingUser;
use App\Services\Conversions\FacebookConversionsService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FacebookAction extends IntegrationAction
{
    public function __construct(private readonly FacebookConversionsService $service, private readonly Request $request)
    {
    }

    public function event5(FacebookPixel $account, TrackingUser $user): ?array
    {
        $event = match ($this->request->query('event')) {
            'new' => 'New',
            'onboarding' => 'Onboarding',
            'purchase' => 'Purchase',
            'active' => 'Active',
            default => null
        };

        if (!$event) {
            return null;
        }

        $data = $this->service->getEventData($user, $event, 'https://memhustle.com/');

        if ($value = $this->request->query('value')) {
            $data['custom_data'] = [
                'value' => (float)$value,
                'currency' => 'USD'
            ];
        }

        return $data;
    }

    public function event4(FacebookPixel $account, TrackingUser $user): ?array
    {
        if (!$event = match ($this->request->query('event')) {
            'lead' => 'Lead',
            'send' => 'Send',
            'purchase' => 'Purchase',
            default => null
        }) {
            return null;
        }

        $eventData = $this->service->getEventData($user, $event, 'https://shop.azi.com.ua/');
        if ($amount = $this->request->query('value')) {
            $eventData['custom_data'] = [
                'value' => (float)$amount,
                'currency' => 'UAH'
            ];
        }

        return $eventData;
    }

    public function event12(FacebookPixel $account, TrackingUser $user): ?array
    {
        $event = match ($this->request->query('event')) {
            'reg' => 'CompleteRegistration',
            'ftd' => 'Purchase',
            default => null
        };

        if (!$event) {
            return null;
        }

        $eventData = $this->service->getEventData($user, $event, 'https://wheeltj.online/');

        $eventData += [
            'custom_data' => $event === 'CompleteRegistration'
                ? ['status' => true]
                : [
                    'value' => $this->request->query('amount', 1),
                    'currency' => $this->request->query('currency', $account->currency)
                ]
        ];

        return $eventData;
    }

    public function event18(FacebookPixel $account, TrackingUser $user): ?array
    {
        if (!$event = $this->request->query('event')) {
            return null;
        }

        $event = str_replace('_', '', Str::convertCase($event, 2));

        $eventData = $this->service->getEventData($user, $event, 'https://nikko.ua/');
        if ($amount = $this->request->query('value')) {
            $eventData['custom_data'] = [
                'value' => (float)$amount,
                'currency' => $this->request->query('currency') ?: 'UAH'
            ];
        }

        return $eventData;
    }

    public function event19(FacebookPixel $account, TrackingUser $user): ?array
    {
        $event = match ($this->request->query('event')) {
            'reg' => 'CompleteRegistration',
            'ftd' => 'Purchase',
            default => null
        };

        if (!$event) {
            return null;
        }

        $eventData = $this->service->getEventData($user, $event, 'https://jamlive.site/');

        $eventData += [
            'custom_data' => $event === 'CompleteRegistration'
                ? ['status' => true]
                : [
                    'value' => $this->request->query('amount', 1),
                    'currency' => $this->request->query('currency', $account->currency)
                ]
        ];

        return $eventData;
    }

    public function event31(FacebookPixel $account, TrackingUser $user): ?array
    {
        $postData = $this->request->post();
        $orderData = $postData['data'] ?? [];

        $externalId = $orderData['externalIDKoristuvaca'] ?? null;
        if (!$externalId) {
            return null;
        }

        $event = $this->request->query('event');

        if (empty($orderData)) {
            if (!$event = match ($event) {
                'lead' => 'Lead',
                'purchase' => 'Purchase',
                'contact' => 'Contact',
                default => null
            }) {
                return null;
            }

            $eventData = $this->service->getEventData($user, $event, $this->request->query('event_source_url', 'https://exactly.salesdrive.me/'));

            $amount = (float)($this->request->query('amount') ?? 0);
            $currency = $this->request->query('currency') ?? $account->currency ?? 'UAH';
            $contentIds = $this->request->query('content_ids');
            $contentType = $this->request->query('content_type', 'product');

            if ($contentIds && is_string($contentIds)) {
                $contentIds = explode(',', $contentIds);
            }

            $customData = [
                'currency' => $currency,
            ];

            if ($amount > 0) {
                $customData['value'] = $amount;
            }

            if ($contentIds) {
                $customData['content_ids'] = $contentIds;
                $customData['content_type'] = $contentType;
            }

            if ($items = $this->request->post('items') ?? $this->request->post('products')) {
                $customData['contents'] = array_map(function ($item) {
                    return [
                        'id' => $item['id'] ?? null,
                        'quantity' => $item['quantity'] ?? 1,
                        'item_price' => $item['item_price'] ?? $item['price'] ?? 0,
                    ];
                }, $items);
            }

            $eventData['custom_data'] = $customData;

            return $eventData;
        }

        $webhookType = $postData['info']['webhookType'] ?? null;
        $webhookEvent = $postData['info']['webhookEvent'] ?? null;

        if ($webhookType !== 'order' || $webhookEvent !== 'status_change') {
            return null;
        }

        $paymentAmount = (float)($orderData['paymentAmount'] ?? 0);
        $products = $orderData['products'] ?? [];
        $utmPage = $orderData['utmPage'] ?? 'https://exactly.salesdrive.me/';

        // Для webhook від Tilda завжди використовуємо подію з query параметра, або 'lead' за замовчуванням
        if (!$event) {
            $event = 'lead';
        }

        if (!$eventName = match ($event) {
            'lead' => 'Lead',
            'purchase' => 'Purchase',
            'contact' => 'Contact',
            default => null
        }) {
            return null;
        }

        $eventData = $this->service->getEventData($user, $eventName, $utmPage);

        $customData = [
            'currency' => 'UAH',
        ];

        if ($paymentAmount > 0) {
            $customData['value'] = $paymentAmount;
        }

        if (!empty($products)) {
            $customData['content_ids'] = array_map(function ($product) {
                return (string)($product['productId'] ?? $product['parameter'] ?? '');
            }, $products);

            $customData['contents'] = array_map(function ($product) {
                return [
                    'id' => (string)($product['productId'] ?? $product['parameter'] ?? ''),
                    'quantity' => $product['amount'] ?? 1,
                    'item_price' => $product['price'] ?? 0,
                ];
            }, $products);

            $customData['content_type'] = 'product';
        }

        $eventData['custom_data'] = $customData;

        return $eventData;
    }

    public function event7(FacebookPixel $account, TrackingUser $user): ?array
    {
        $postData = $this->request->post();

        $queryEvent = $this->request->query('event');
        $event = match ($queryEvent) {
            'lead' => 'Lead',
            'purchase' => 'Purchase',
            default => null
        };

        if (!$event) {
            $eventType = $postData['event'] ?? null;

            if ($eventType === 'order.change_order_status') {
                $orderData = $postData['context'] ?? $postData;
                $grandTotal = (float)($orderData['grand_total'] ?? 0);

                $event = $grandTotal > 0 ? 'Purchase' : 'Lead';
            }
        }

        if (!$event) {
            return null;
        }

        $eventData = $this->service->getEventData($user, $event, 'https://king-bbq.com.ua/');

        $orderData = $postData['context'] ?? $postData;
        $customData = [];

        if ($event === 'Purchase') {
            $amount = 0;
            $currency = $account->currency;

            if (!empty($orderData)) {
                $amount = (float)($orderData['grand_total'] ?? $orderData['products_total'] ?? $orderData['payments_total'] ?? 0);
            }

            if ($amount == 0) {
                $amount = (float)($this->request->query('amount', $this->request->query('value', 1)));
            }

            $currency = $this->request->query('currency', $currency);

            $customData = [
                'value' => $amount,
                'currency' => $currency,
                'content_type' => 'product'
            ];

            if (!empty($orderData)) {
                if (isset($orderData['source_uuid'])) {
                    $customData['order_id'] = (string)$orderData['source_uuid'];
                }
                if (isset($orderData['id'])) {
                    $customData['content_name'] = 'Order #' . $orderData['id'];
                }

                $products = $orderData['products'] ?? $orderData['items'] ?? $this->request->post('products') ?? $this->request->post('items') ?? [];

                if (empty($products) && $this->request->query('products')) {
                    $productsJson = $this->request->query('products');
                    if (is_string($productsJson)) {
                        $decoded = json_decode($productsJson, true);
                        if (is_array($decoded)) {
                            $products = $decoded;
                        }
                    }
                }

                if (!empty($products) && is_array($products)) {
                    $customData['contents'] = array_map(function ($product) {
                        return [
                            'id' => (string)($product['id'] ?? $product['product_id'] ?? $product['productId'] ?? ''),
                            'quantity' => (int)($product['quantity'] ?? $product['amount'] ?? 1),
                            'item_price' => (float)($product['item_price'] ?? $product['price'] ?? 0),
                        ];
                    }, $products);
                } elseif ($amount > 0 && isset($orderData['id'])) {
                    $customData['contents'] = [[
                        'id' => (string)$orderData['id'],
                        'quantity' => 1,
                        'item_price' => $amount
                    ]];
                }
            }
        } else {
            $customData['status'] = true;
            $customData['content_type'] = 'product';

            if (!empty($orderData)) {
                $currency = $this->request->query('currency', $account->currency);
                if ($currency) {
                    $customData['currency'] = $currency;
                }

                $potentialValue = (float)($orderData['grand_total'] ?? $orderData['products_total'] ?? $orderData['payments_total'] ?? 0);
                if ($potentialValue > 0) {
                    $customData['value'] = $potentialValue;
                }

                if (isset($orderData['source_uuid'])) {
                    $customData['content_name'] = 'Lead Order #' . $orderData['source_uuid'];
                }
                if (isset($orderData['id'])) {
                    $customData['content_ids'] = [(string)$orderData['id']];
                }

                // Шукаємо продукти в різних місцях
                $products = $orderData['products'] ?? $orderData['items'] ?? $this->request->post('products') ?? $this->request->post('items') ?? [];

                // Якщо products передано через query як JSON
                if (empty($products) && $this->request->query('products')) {
                    $productsJson = $this->request->query('products');
                    if (is_string($productsJson)) {
                        $decoded = json_decode($productsJson, true);
                        if (is_array($decoded)) {
                            $products = $decoded;
                        }
                    }
                }

                if (!empty($products) && is_array($products)) {
                    $customData['contents'] = array_map(function ($product) {
                        return [
                            'id' => (string)($product['id'] ?? $product['product_id'] ?? $product['productId'] ?? ''),
                            'quantity' => (int)($product['quantity'] ?? $product['amount'] ?? 1),
                            'item_price' => (float)($product['item_price'] ?? $product['price'] ?? 0),
                        ];
                    }, $products);
                } elseif ($potentialValue > 0 && isset($orderData['id'])) {
                    // Якщо немає продуктів, але є сума - створюємо contents на основі замовлення
                    $customData['contents'] = [[
                        'id' => (string)$orderData['id'],
                        'quantity' => 1,
                        'item_price' => $potentialValue
                    ]];
                }
            }
        }

        $eventData += [
            'custom_data' => $customData
        ];

        return $eventData;
    }

    public function event37(FacebookPixel $account, TrackingUser $user): ?array
    {
        $event = match ($this->request->query('event')) {
            'reg' => 'CompleteRegistration',
            'ftd' => 'Purchase',
            default => null
        };

        if (!$event) {
            return null;
        }

        $eventData = $this->service->getEventData($user, $event, 'https://25union.com.ua/');

        $eventData += [
            'custom_data' => $event === 'CompleteRegistration'
                ? ['status' => true]
                : [
                    'value' => $this->request->query('amount', 1),
                    'currency' => $this->request->query('currency', $account->currency)
                ]
        ];

        return $eventData;
    }

    public function event46(FacebookPixel $account, TrackingUser $user): ?array
    {

        $eventParam = $this->request->query('event');
        $amount = $this->request->query('amount') ?? $this->request->query('value');

        if ($eventParam === 'connect_wallet') {
            $event = 'ConnectWallet';
        } elseif ($eventParam === 'deposit' || $amount !== null) {
            $event = 'Purchase';
        } else {
            $event = 'ConnectWallet';
        }

        $eventData = $this->service->getEventData($user, $event, $this->request->query('event_source_url') ?? 'https://trust-lend.com/');

        if ($event === 'Purchase') {
            $eventData += [
                'custom_data' => [
                    'value' => (float)($amount ?? 1),
                    'currency' => $this->request->query('currency') ?? $account->currency
                ]
            ];
        } else {
            $eventData += [
                'custom_data' => [
                    'status' => true
                ]
            ];
        }

        return $eventData;
    }
}
