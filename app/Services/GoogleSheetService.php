<?php

namespace App\Services;

use App\Models\GoogleSheetAccount;
use App\Services\GoogleSheetDataPreparer;
use DigitalStars\Sheets\DSheets;
use Google\Service\Sheets\ValueRange;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Throwable;

class GoogleSheetService
{
    public function __construct(protected GoogleSheetDataPreparer $dataPreparer)
    {
    }

    public function getContentJS($id, bool $forBandle = false): string
    {
        $account = GoogleSheetAccount::find($id);
        if (!$account) {
            $content = !$forBandle ? 'console.log("Account not found. Please remove this script from your site.")' : '';
        }else{
            $file = resource_path('js/google-sheets/index.min.js');
            $content = file_get_contents($file);
            $content = str_replace('11111', $id, $content);
        }

        if (!$forBandle){
            File::put(public_path("partners/js/google-sheets-$id.js"), $content);
        }

        return $content;
    }


    public function handle(GoogleSheetAccount $account, $data): void
    {
        $now = date('Y-m-d H:i:s');

        if (!is_array($data)) {
            $data = [];
        }
        $data = array_is_list($data) ? $data : [$data];

        $isNotEmptyParam = trim((string) request()->query('is_not_empty', ''));
        if ($isNotEmptyParam !== '') {
            $fields = array_values(array_filter(array_map('trim', explode(',', $isNotEmptyParam))));
            $rejectRaw = trim((string) request()->query('reject_values', ''));
            $reject = $rejectRaw !== '' ? array_map(fn($s) => mb_strtolower(trim($s)), explode(',', $rejectRaw)) : ['(not provided)', 'null', 'undefined', ''];

            $norm = fn($v) => trim((string) ($v ?? ''));
            $valid = function (array $row) use ($fields, $reject, $norm): bool {
                foreach ($fields as $f) {
                    $val = mb_strtolower($norm(data_get($row, $f)));
                    if ($val === '' || in_array($val, $reject, true)) {
                        return false;
                    }
                }
                return true;
            };

            $data = array_values(array_filter($data, fn($row) => is_array($row) && $valid($row)));
            if (!$data) {
                return;
            }
        }

        // Для account 29 використовуємо спеціальну обробку
        if ($account->id === 29) {
            // Для account 29 додаємо заголовки тільки якщо їх ще немає (has_header = false)
            $insert = $this->dataPreparer->prepareRowsForAccount29($data, $now, $account);
        } elseif ($account->id === 32) {
            // Account 32: заголовок з полів (коли немає), рядки з колонки A по порядку
            $prepared = $this->dataPreparer->prepare($data, 32);
            $prepared = array_values(array_filter($prepared, fn($row) => is_array($row) && !empty($row)));
            if (empty($prepared)) {
                return;
            }
            $rows = array_map(static fn(array $row) => array_map(static fn($v) => $v ?? '', array_values($row)), $prepared);
            if (!$account->has_header) {
                array_unshift($rows, array_keys($prepared[0]));
                $account->update(['has_header' => true]);
            }
            $insert = $rows;
        } else {
            $insert = collect($data)
                ->filter(fn($item) => !empty($item) && is_array($item))
                ->map(function (array $item) use ($now) {
                    $itemDate = $now;
                    if (!empty($item['created_at'])) {
                        $ts = strtotime($item['created_at']);
                        $itemDate = $ts ? date('Y-m-d H:i:s', $ts) : $now;
                        unset($item['created_at']);
                    }
                    $values = array_map(static fn($v) => is_null($v) ? '' : $v, $item);
                    return array_merge([$itemDate], array_values($values));
                })
                ->when(!$account->has_header, function (Collection $collect) use ($account, $data) {
                    $first = $data[0] ?? [];
                    unset($first['created_at']);
                    $collect->prepend(array_merge(['Date Time'], array_keys($first)));
                    $account->update(['has_header' => true]);
                })
                ->toArray();
        }

        if (!$insert) {
            return;
        }

        dispatch(function () use ($account, $insert) {
            try {
                $this->addDataInTable($account->spreadsheet_id, $account->sheet_id, $insert);
            } catch (\Throwable $e) {
                \Log::error('Google Sheet Service ' . $account->id . ' : Failed', $insert);
                report($e);
            }
        })->afterResponse();
    }

    public function addDataInTable($spreadsheetId, $sheetId, array $data): void
    {
        if (empty($data) || !is_array($data[0]) || !array_is_list($data[0])) {
            if (!empty($data)) {
                \Log::error('Google Sheet Service: Invalid data format', ['first_item' => $data[0] ?? null]);
            }
            return;
        }
        $googleAccountKeyFilePath = storage_path('keys/core-dominion-260616-fe416c814fff.json');
        $sheet = DSheets::create($spreadsheetId, $googleAccountKeyFilePath)->setSheet($sheetId);
        retry(3, function () use ($sheet, $spreadsheetId, $sheetId, $data) {
            // Завжди з колонки A, INSERT_ROWS — нові рядки після останнього
            $service = $sheet->getService();
            $range = $sheetId . '!A:A';
            $body = new ValueRange(['values' => $data]);
            $service->spreadsheets_values->append($spreadsheetId, $range, $body, [
                'valueInputOption' => 'RAW',
                'insertDataOption' => 'INSERT_ROWS',
            ]);
        }, 2000);
    }
}
