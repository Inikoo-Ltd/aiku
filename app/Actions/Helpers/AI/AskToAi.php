<?php

namespace App\Actions\Helpers\AI;

use App\Actions\OrgAction;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class AskToAi extends OrgAction
{
    /**
     * Send a prompt to AI and get a string response.
     * Reuses configuration from auto-translations (ChatGPT5 driver) for consistency.
     *
     * @param string $prompt
     * @param string $model (Optional, default 'gpt-4o-mini')
     * @return string|null
     */
    public function handle(string $prompt, string $model = 'gpt-4o-mini'): ?string
    {
        if (empty($prompt)) {
            return null;
        }

        try {
            $driverName = config('auto-translations.default_driver', 'chatgpt5');
            $driverConfig = config("auto-translations.drivers.{$driverName}");
            $apiKey = $driverConfig['api_key'] ?? null;

            if (empty($apiKey)) {
                $apiKey = config('askbot-laravel.openai_api_key');
            }

            if (empty($apiKey)) {
                Log::error("AskToAi: Missing API Key (checked auto-translations and askbot config)");
                return null;
            }

            $url = 'https://api.openai.com/v1/chat/completions';

            $response = Http::withToken($apiKey)
                ->timeout(30)
                ->post($url, [
                    'model' => $model,
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'You are a helpful CRM assistant. Provide a concise response based on the user prompt.'
                        ],
                        [
                            'role' => 'user',
                            'content' => $prompt
                        ],
                    ],
                    'temperature' => 0.3,
                ]);

            if (!$response->successful()) {
                $errorMsg = "AskToAi API Error: " . $response->body();
                Log::error($errorMsg);
                return null;
            }

            $content = $response->json('choices.0.message.content');
            return trim($content);
        } catch (Throwable $e) {
            $errorMsg = "AskToAi Exception: " . $e->getMessage();
            Log::error($errorMsg);
            return null;
        }
    }
}
