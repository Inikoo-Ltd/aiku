<?php

namespace App\Actions\Helpers\AI;

use App\Actions\Helpers\AI\Traits\WithAICreditErrorHandler;
use App\Actions\Helpers\AI\Traits\WithAIGateway;
use App\Actions\OrgAction;
use App\Exceptions\AICreditException;
use Illuminate\Support\Facades\Log;
use Throwable;

class AskToAi extends OrgAction
{
    use WithAICreditErrorHandler;
    use WithAIGateway;

    /**
     * Send a prompt to AI and get a string response.
     * Reuses configuration from auto-translations (ChatGPT driver) for consistency.
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
            $apiKey = $this->getApiKey();

            if (empty($apiKey)) {
                Log::error("AskToAi: Missing API Key (checked auto-translations config)");
                return null;
            }

            return $this->sendRequest($apiKey, $model, $prompt);
        } catch (AICreditException $e) {
            throw $e;
        } catch (Throwable $e) {
            Log::error("AskToAi Exception: " . $e->getMessage());
            $this->rethrowAICreditThrowable($e);

            return null;
        }
    }

    private function getApiKey(): ?string
    {
        return $this->aiApiKey(config('askbot-laravel.openai_api_key'));
    }

    private function sendRequest(string $apiKey, string $model, string $prompt): ?string
    {
        $response = $this->aiRequest($apiKey)
            ->connectTimeout(10)
            ->timeout(30)
            ->post('chat/completions', array_merge([
                'model' => $this->aiModel($model),
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
            ], str_starts_with($model, 'gpt-4') || str_starts_with($model, 'gpt-3') ? ['temperature' => 0.3] : []));

        if (!$response->successful()) {
            Log::error("AskToAi API Error: " . $response->body());
            $this->guardAICreditResponse($response);

            return null;
        }

        $content = $response->json('choices.0.message.content');

        if (!is_string($content)) {
            Log::warning('AskToAi API response content missing', [
                'response' => $response->json(),
            ]);
            return null;
        }

        $content = trim($content);

        return $content === '' ? null : $content;
    }
}
