<?php

namespace App\Services;

use App\Models\GoogleSheetAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class GoogleSheetDataPreparer
{
    public function prepare(array $data, int $accountId): array
    {
        return match ($accountId) {
            24, 25, 26 => $this->prepareForAccount24($data),
            29 => $this->prepareForAccount29($data),
            30 => $this->prepareForAccount30($data),
            32 => $this->prepareForCrmRow($data),
            default => array_is_list($data) ? $data : [$data],
        };
    }

    public function prepareRowsForAccount29(array $data, string $now, GoogleSheetAccount $account): array
    {
        $headers = $this->getHeadersForAccount29();
        $result = [];

        // Для account 29 додаємо заголовки тільки один раз, якщо їх ще немає
        if (!$account->has_header) {
            // Заголовки як масив значень (не ключі) - це буде перший рядок
            // Кожен заголовок - це значення в масиві, яке буде записано в окрему колонку A, B, C, тощо
            $result[] = $headers;
            $account->update(['has_header' => true]);
        }

        // Обробляємо дані в правильному порядку відповідно до заголовків
        foreach ($data as $item) {
            if (!is_array($item) || empty($item)) {
                continue;
            }

            // Формуємо рядок як масив значень в правильному порядку
            // Кожен рядок - це масив значень, який буде додано як один рядок в таблицю
            $row = [$now]; // Date Time завжди перший
            foreach (array_slice($headers, 1) as $header) {
                $value = $item[$header] ?? '';
                // Конвертуємо значення в рядок для правильного відображення
                if (is_bool($value)) {
                    $value = $value ? '1' : '0';
                } elseif (is_array($value) || is_object($value)) {
                    $value = json_encode($value);
                } elseif (is_null($value)) {
                    $value = '';
                } else {
                    $value = (string)$value;
                }
                $row[] = $value;
            }
            $result[] = $row;
        }

        return $result;
    }

    private function getHeadersForAccount29(): array
    {
        return [
            'Date Time',
            'event_id',
            'event_type',
            'lang_code',
            'timestamp',
            'var_symbol',
            'order_id',
            'order_num',
            'order_guid',
            'order_lang',
            'order_paid',
            'order_tips',
            'order_type',
            'order_status',
            'order_payBy',
            'order_table',
            'order_total',
            'order_subTotal',
            'order_currency',
            'order_discount',
            'order_cutlery',
            'order_timezone',
            'order_costOfPack',
            'order_external',
            'order_localTime',
            'order_billLocalTime',
            'order_customerComments',
            'items_count',
            'items_data',
            'delivery_cost',
            'delivery_when',
            'delivery_when_utc',
            'delivery_status',
            'delivery_posID',
            'delivery_comment',
            'delivery_statusMessage',
            'delivery_time_pickup',
            'customer_name',
            'customer_email',
            'customer_phone',
            'customer_city',
            'customer_state',
            'customer_streetName',
            'customer_streetNumber',
            'customer_apartment',
            'customer_sublocality',
            'customer_address_prediction',
            'location_id',
            'location_area',
            'location_name',
            'location_type',
            'location_pos_id',
            'timestamp_created',
            'timestamp_payment',
            'timestamp_approved',
            'preparing_time',
            'delivering_time',
            'additional_fees',
            'discount_area',
            'discount_loyalty',
            'discount_promocode',
            'loyalty_bonus_useAmount',
            'loyalty_bonus_bonusAmount',
            'payment_rro_authcode',
            'payment_rro_rrnDebit',
            'payment_rro_merchantId',
            'payment_rro_terminalId',
            'payment_rro_transactionId',
            'payment_card_type',
            'payment_card_last4',
            'utm_source',
            'utm_medium',
            'utm_campaign',
            'utm_content',
            'utm_term',
        ];
    }

    private function prepareForAccount24(array $data): array
    {
        $normalized = array_is_list($data) ? $data : [$data];

        $statusMap = [
            1  => 'НОВИЙ',
            4  => 'ОЧІКУВАННЯ ПЕРЕДОПЛАТИ',
            12 => 'ВИКОНАНО',
            14 => 'НЕДОЗВОН',
            19 => 'СКАСОВАНО',
        ];

        $result = [];

        foreach ($normalized as $row) {
            if (!isset($row['context']) || !is_array($row['context'])) {
                continue;
            }

            $c = $row['context'];

            $amount = isset($c['grand_total'])
                ? number_format((float)$c['grand_total'], 2, ',', '')
                : '';

            $createdSerial = $this->isoToGoogleSerial($c['created_at'] ?? null);
            $updatedSerial = $this->isoToGoogleSerial($c['updated_at'] ?? $c['created_at'] ?? null);

            $result[] = [
                (string)($c['id'] ?? ''),
                $amount,
                $createdSerial,
                $updatedSerial,
                (string)($statusMap[$c['status_id'] ?? null] ?? 'Невідомо'),
            ];
        }

        return $result;
    }

    private function prepareForAccount29(array $data): array
    {
        $normalized = array_is_list($data) ? $data : [$data];
        $result = [];

        foreach ($normalized as $row) {
            // Перевірка типу події - обробляємо тільки order.accepted
            $eventType = $row['type'] ?? ($row['data']['type'] ?? null);
            if ($eventType !== 'order.accepted') {
                continue; // Ігноруємо інші типи подій (delivery.update тощо)
            }

            // Обробка структури order.accepted
            $order = $row['data'] ?? $row;

            // Обробка items
            $items = $order['items'] ?? [];
            $itemsData = [];
            foreach ($items as $item) {
                $itemName = '';
                if (isset($item['name']['uk']['name'])) {
                    $itemName = $item['name']['uk']['name'];
                }

                $itemsData[] = [
                    'id' => $item['_id'] ?? '',
                    'name' => $itemName,
                    'description' => $item['name']['uk']['description'] ?? '',
                    'pack' => $item['pack'] ?? '',
                    'count' => $item['count'] ?? 0,
                    'posID' => $item['posID'] ?? '',
                    'price' => $item['price'] ?? 0,
                    'total' => $item['total'] ?? 0,
                    'alcohol' => $item['alcohol'] ?? false,
                    'recommendation' => $item['recommendation'] ?? false,
                ];
            }

            // Формування payload
            $payload = [
                // Основна інформація про подію
                'event_id' => $row['id'] ?? '',
                'event_type' => $row['type'] ?? '',
                'lang_code' => $row['langCode'] ?? '',
                'timestamp' => $row['timestamp'] ?? '',
                'var_symbol' => $row['varSymbol'] ?? '',

                // Інформація про замовлення
                'order_id' => $order['_id'] ?? '',
                'order_num' => $order['num'] ?? '',
                'order_guid' => $order['guid'] ?? '',
                'order_lang' => $order['lang'] ?? '',
                'order_paid' => $order['paid'] ?? false,
                'order_tips' => $order['tips'] ?? 0,
                'order_type' => $order['type'] ?? '',
                'order_status' => $order['status'] ?? '',
                'order_payBy' => $order['payBy'] ?? '',
                'order_table' => $order['table'] ?? '',
                'order_total' => $order['total'] ?? 0,
                'order_subTotal' => $order['subTotal'] ?? 0,
                'order_currency' => $order['currency'] ?? '',
                'order_discount' => $order['discount'] ?? 0,
                'order_cutlery' => $order['cutlery'] ?? '',
                'order_timezone' => $order['timezone'] ?? '',
                'order_costOfPack' => $order['costOfPack'] ?? 0,
                'order_external' => $order['external'] ?? '',
                'order_localTime' => $order['orderLocalTime'] ?? '',
                'order_billLocalTime' => $order['orderBillLocalTime'] ?? '',
                'order_customerComments' => $order['customerComments'] ?? '',

                // Інформація про продукти
                'items_count' => count($items),
                'items_data' => json_encode($itemsData),

                // Інформація про доставку
                'delivery_cost' => data_get($order, 'delivery.cost', 0),
                'delivery_when' => data_get($order, 'delivery.when', ''),
                'delivery_when_utc' => data_get($order, 'delivery.whenUTC', ''),
                'delivery_status' => data_get($order, 'delivery.status', ''),
                'delivery_posID' => data_get($order, 'delivery.posID', ''),
                'delivery_comment' => data_get($order, 'delivery.comment', ''),
                'delivery_statusMessage' => data_get($order, 'delivery.statusMessage', ''),
                'delivery_time_pickup' => data_get($order, 'delivery.extra.timePickup', ''),

                // Інформація про клієнта
                'customer_name' => data_get($order, 'delivery.customer.name', ''),
                'customer_email' => data_get($order, 'delivery.customer.email', ''),
                'customer_phone' => data_get($order, 'delivery.customer.phone', ''),
                'customer_city' => data_get($order, 'delivery.customer.address.city', ''),
                'customer_state' => data_get($order, 'delivery.customer.address.state', ''),
                'customer_streetName' => data_get($order, 'delivery.customer.address.streetName', ''),
                'customer_streetNumber' => data_get($order, 'delivery.customer.address.streetNumber', ''),
                'customer_apartment' => data_get($order, 'delivery.customer.address.apartment', ''),
                'customer_sublocality' => data_get($order, 'delivery.customer.address.sublocality') ?: 'Пусто',
                'customer_address_prediction' => data_get($order, 'delivery.customer.address.prediction', ''),

                // Інформація про локацію
                'location_id' => data_get($order, 'location._id', ''),
                'location_area' => data_get($order, 'location.area', ''),
                'location_name' => data_get($order, 'location.i18n.ru.name', ''),
                'location_type' => data_get($order, 'location.type', ''),
                'location_pos_id' => data_get($order, 'location.posID', ''),

                // Таймстемпи
                'timestamp_created' => data_get($order, 'timestamps.created', ''),
                'timestamp_payment' => data_get($order, 'timestamps.payment', ''),
                'timestamp_approved' => data_get($order, 'timestamps.approved', ''),

                // Додаткова інформація
                'preparing_time' => data_get($order, 'preparingTime', ''),
                'delivering_time' => data_get($order, 'deliveringTime', ''),
                'additional_fees' => data_get($order, 'additionalFees', ''),

                // Знижки
                'discount_area' => data_get($order, 'discountData.areaDiscount', 0),
                'discount_loyalty' => data_get($order, 'discountData.loyaltyDiscount', 0),
                'discount_promocode' => data_get($order, 'discountData.promocodeDiscount', 0),

                // Лояльність
                'loyalty_bonus_useAmount' => data_get($order, 'loyalty.bonus.useAmount', 0),
                'loyalty_bonus_bonusAmount' => data_get($order, 'loyalty.bonus.bonusAmount', 0),

                // Платіжні деталі
                'payment_rro_authcode' => data_get($order, 'paymentCustomerDetails.rro.authcode', ''),
                'payment_rro_rrnDebit' => data_get($order, 'paymentCustomerDetails.rro.rrnDebit', ''),
                'payment_rro_merchantId' => data_get($order, 'paymentCustomerDetails.rro.merchantId', ''),
                'payment_rro_terminalId' => data_get($order, 'paymentCustomerDetails.rro.terminalId', ''),
                'payment_rro_transactionId' => data_get($order, 'paymentCustomerDetails.rro.transactionId', ''),
                'payment_card_type' => data_get($order, 'paymentCustomerDetails.card.type', ''),
                'payment_card_last4' => data_get($order, 'paymentCustomerDetails.card.last4', ''),

                // Marketing UTM
                'utm_source' => data_get($order, 'marketing.utm.source', ''),
                'utm_medium' => data_get($order, 'marketing.utm.medium', ''),
                'utm_campaign' => data_get($order, 'marketing.utm.campaign', ''),
                'utm_content' => data_get($order, 'marketing.utm.content', ''),
                'utm_term' => data_get($order, 'marketing.utm.term', ''),
            ];

            $result[] = $payload;
        }

        return $result;
    }

    private function prepareForAccount30(array $data): array
    {
        $normalized = array_is_list($data) ? $data : [$data];
        $result = [];

        foreach ($normalized as $row) {
            if (!is_array($row) || empty($row)) {
                continue;
            }

            // Обробка products - конвертуємо масив в JSON рядок
            $products = $row['products'] ?? [];
            $productsJson = '';
            if (is_array($products) && !empty($products)) {
                $productsJson = json_encode($products, JSON_UNESCAPED_UNICODE);
            }

            // Формуємо структурований об'єкт для запису в Google Sheets
            $prepared = [
                'order_id' => (string)($row['order_id'] ?? ''),
                'name' => (string)($row['name'] ?? ''),
                'email' => (string)($row['email'] ?? ''),
                'phone' => (string)($row['phone'] ?? ''),
                'utm_source' => (string)($row['utm_source'] ?? ''),
                'utm_medium' => (string)($row['utm_medium'] ?? ''),
                'utm_campaign' => (string)($row['utm_campaign'] ?? ''),
                'utm_content' => (string)($row['utm_content'] ?? ''),
                'utm_term' => (string)($row['utm_term'] ?? ''),
                'order_total' => (string)($row['order_total'] ?? ''),
                'order_currency' => (string)($row['order_currency'] ?? ''),
                'products' => $productsJson,
                'external_id' => (string)($row['external_id'] ?? ''),
            ];

            $result[] = $prepared;
        }

        return $result;
    }

    /**
     * CRM row для sheet 32 (бандл 45 → gads 24 + sheet 32).
     * Поля: event, order_number, status, created_at, ordered_at, event_date, event_time, phone, email, order_sum, people_count, order_type, city, office, utm_*, gclid, external_id, is_direct, webhook_executed_at.
     */
    private function prepareForCrmRow(array $data): array
    {
        $normalized = array_is_list($data) ? $data : [$data];
        $result = [];

        foreach ($normalized as $row) {
            if (!is_array($row) || empty($row)) {
                continue;
            }

            $result[] = [
                'event' => (string)($row['event'] ?? ''),
                'order_number' => (string)($row['order_number'] ?? ''),
                'status' => (string)($row['status'] ?? ''),
                'created_at' => (string)($row['created_at'] ?? ''),
                'ordered_at' => (string)($row['ordered_at'] ?? ''),
                'event_date' => (string)($row['event_date'] ?? ''),
                'event_time' => (string)($row['event_time'] ?? ''),
                'phone' => (string)($row['phone'] ?? ''),
                'email' => (string)($row['email'] ?? ''),
                'order_sum' => (string)($row['order_sum'] ?? ''),
                'people_count' => (string)($row['people_count'] ?? ''),
                'order_type' => (string)($row['order_type'] ?? ''),
                'city' => (string)($row['city'] ?? ''),
                'office' => (string)($row['office'] ?? ''),
                'utm_source' => (string)($row['utm_source'] ?? ''),
                'utm_medium' => (string)($row['utm_medium'] ?? ''),
                'utm_campaign' => (string)($row['utm_campaign'] ?? ''),
                'utm_content' => (string)($row['utm_content'] ?? ''),
                'utm_term' => (string)($row['utm_term'] ?? ''),
                'gclid' => (string)($row['gclid'] ?? ''),
                'external_id' => (string)($row['external_id'] ?? ''),
                'is_direct' => (string)($row['is_direct'] ?? ''),
                'webhook_executed_at' => (string)($row['webhook_executed_at'] ?? ''),
            ];
        }

        return $result;
    }

    private function isoToGoogleSerial(?string $dateTime): ?float
    {
        if (empty($dateTime)) {
            return null;
        }

        try {
            $dt = is_numeric($dateTime)
                ? Carbon::createFromTimestamp((int)$dateTime, 'UTC')
                : Carbon::parse($dateTime, 'UTC');

            $ts = $dt->timestamp;
            $serial = ($ts / 86400) + 25569;

            return (float)$serial;
        } catch (\Exception $e) {
            Log::warning('isoToGoogleSerial failed', ['val' => $dateTime, 'err' => $e->getMessage()]);
            return null;
        }
    }
}
