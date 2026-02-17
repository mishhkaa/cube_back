<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AiChatController extends Controller
{
    private function getSystemPrompt(): string
    {
        return <<<'PROMPT'
Ти — AI-помічник адмін-панелі Cube. Відповідай українською. Ти знаєш структуру проєкту і допомагаєш з питаннями по адмінці, API, інтеграціях.

**Структура Cube:**
- Клієнти (clients) — картки проєктів з полями: name, link, unit, niche, description, відповідальні (users), ad_sources
- Integrations FB CAPI — пікселі Meta, потрібні: partner_id (pixel_id), access_token, name
- Script bundles — збирають інтеграції в один JS для вставки на сайт
- Ads Sources — джерела реклами (fb, tiktok, gads) для відстеження витрат
- API: Laravel, Sanctum auth, REST

**Щоб створити CAPI + скрипт для проєкту:**
1. FB CAPI: POST /api/integrations/fb-capi — { name, partner_id (pixel_id), access_token }
2. Script bundle: POST /api/integrations/script-bundles — прив'язати інтеграції
3. Клієнт: POST /api/clients — { name, link, unit, niche, description, user_ids, ad_source_ids }
4. Готовий скрипт — GET /api/integrations/script-bundles/js-content або через bundle

**Користувач може питати:** як налаштувати піксель, які поля потрібні, як згенерувати скрипт, що робити з помилками, як працює CAPI тощо.

Давай чіткі, практичні відповіді. Якщо потрібно згенерувати код або конфіг — роби це у форматі markdown з підсвіткою.
PROMPT;
    }

    public function __invoke(Request $request): JsonResponse
    {
        $messages = $request->validate([
            'messages' => 'required|array',
            'messages.*.role' => 'required|in:system,user,assistant',
            'messages.*.content' => 'required|string',
        ])['messages'];

        $apiKey = config('services.google_ai.api_key');
        if (!$apiKey) {
            return response()->json(['error' => 'GOOGLE_AI_API_KEY не налаштовано в .env'], 500);
        }

        $contents = [];
        foreach ($messages as $m) {
            $role = $m['role'] === 'assistant' ? 'model' : 'user';
            $contents[] = [
                'role' => $role,
                'parts' => [['text' => $m['content']]],
            ];
        }

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . $apiKey;

        try {
            $response = Http::timeout(60)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($url, [
                    'system_instruction' => [
                        'parts' => [['text' => $this->getSystemPrompt()]],
                    ],
                    'contents' => $contents,
                    'generationConfig' => [
                        'temperature' => 0.7,
                        'maxOutputTokens' => 2000,
                    ],
                ]);

            if (!$response->successful()) {
                $body = $response->json();
                $errMsg = $body['error']['message'] ?? $body['error']['code'] ?? $response->body();
                Log::error('Google AI API error', [
                    'status' => $response->status(),
                    'body' => $body,
                ]);
                return response()->json([
                    'error' => 'Помилка AI: ' . $errMsg,
                ], 502);
            }

            $text = $response->json('candidates.0.content.parts.0.text', '');
            return response()->json(['content' => trim($text) ?: 'Немає відповіді.']);
        } catch (\Throwable $e) {
            Log::error('AiChat error', ['message' => $e->getMessage()]);
            return response()->json(['error' => 'Помилка: ' . $e->getMessage()], 500);
        }
    }
}
