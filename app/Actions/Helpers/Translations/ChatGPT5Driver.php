<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 Sept 2025 00:29:11 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Translations;

use App\Actions\Helpers\AI\Traits\WithAICreditErrorHandler;
use App\Exceptions\AICreditException;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Arr;
use Throwable;
use TikToken\Encoder;
use VildanBina\LaravelAutoTranslation\Contracts\TranslationDriver;

class ChatGPT5Driver implements TranslationDriver
{
    use WithAICreditErrorHandler;

    private const BUFFER_FACTOR = 2;

    private array $config;

    private Encoder $encoder;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->encoder = new Encoder();
    }

    private function estimateTokens(array $data, string $sourceLang, string $targetLang): int
    {
        try {
            return count($this->encoder->encode(
                json_encode($this->buildPrompt($data, $sourceLang, $targetLang))
            ));
        } catch (Throwable) {
            return PHP_INT_MAX;
        }
    }

    public function translate(array $texts, string $sourceLang, string $targetLang): array
    {
        $translations = [];
        $currentChunk = [];
        $chunks = $this->makeChunks($texts, $sourceLang, $targetLang);

        collect($chunks)
            ->each(function (array $value) use (&$translations, $sourceLang, $targetLang) {
                $chunkResult = $this->sendTranslationRequest($value, $sourceLang, $targetLang);
                $translations += is_array($chunkResult) ? $chunkResult : [];
            });

        if (! empty($currentChunk)) {
            $chunkResult = $this->sendTranslationRequest($currentChunk, $sourceLang, $targetLang);
            $translations += is_array($chunkResult) ? $chunkResult : [];
        }

        return $translations;
    }

    private function makeChunks(array $texts, string $sourceLang, string $targetLang): array
    {
        $maxTokens = $this->config['max_tokens'] ?? 1000;
        $usable = (int) floor($maxTokens / self::BUFFER_FACTOR);
        $chunks = [];
        $current = [];

        foreach ($texts as $key => $text) {
            $test = $current + [$key => $text];
            if ($this->estimateTokens($test, $sourceLang, $targetLang) > $usable) {
                if ($current) {
                    $chunks[] = $current;
                }
                $current = [$key => $text];
            } else {
                $current[$key] = $text;
            }
        }

        if ($current) {
            $chunks[] = $current;
        }

        return $chunks;
    }

    protected function buildPrompt(array $chunk, string $sourceLang, string $targetLang): array
    {
        return [
            [
                'role' => 'system',
                'content' => <<<EOL
You are a helpful assistant that translates text from {$sourceLang} to {$targetLang}.
    IMPORTANT INSTRUCTIONS:
    - The input will always be a JSON object.
    - Do NOT alter or translate any of the keys in the JSON object. Keys must remain exactly as provided.
    - Only translate the values associated with the keys.
    - Return the output as a valid JSON object where:
        - The keys remain unchanged.
        - The values are properly translated.
EOL
            ],
            [
                'role' => 'user',
                'content' => json_encode($this->indexChunk($chunk)),
            ],
        ];
    }

    /**
     * Keying the payload by the source text lets the model collapse entries that differ
     * only by whitespace (" more" and "more" are separate aiku strings), which silently
     * changed the entry count. Numeric keys cannot collide or be "helpfully" tidied.
     *
     * @return array<string, string>
     */
    private function indexChunk(array $chunk): array
    {
        $indexed = [];
        $position = 0;

        foreach ($chunk as $text) {
            $indexed[(string) $position++] = $text;
        }

        return $indexed;
    }

    protected function sendTranslationRequest(array $texts, string $sourceLang, string $targetLang): array
    {
        $prompt = $this->buildPrompt($texts, $sourceLang, $targetLang);

        $response = Http::baseUrl('https://api.openai.com')
            ->withHeaders([
                'Authorization' => 'Bearer '.$this->config['api_key'],
            ])
            ->timeout($this->config['http_timeout'] ?? 30)
            ->post('/v1/chat/completions', [
                'model' => $this->config['model'] ?? 'gpt-5-nano',
                'messages' => $prompt,
                'temperature' => 1,
                'max_completion_tokens' => $this->config['max_tokens'] ?? 1000,
                'response_format' => ['type' => 'json_object'],
            ]);

        if (! $response->successful()) {
            if ($this->isAICreditResponse($response)) {
                throw new AICreditException($this->aiCreditErrorMessage());
            }

            $json = $response->json();
            $errorMessage = is_array($json) ? Arr::get($json, 'error.message') : null;

            throw new Exception('ChatGPT API error: '.($errorMessage ?? $response->body()));
        }

        $json = $response->json();
        $content = is_array($json) ? Arr::get($json, 'choices.0.message.content', '') : '';
        if (! json_validate($content)) {
            throw new Exception('Invalid JSON returned by ChatGPT: '.json_last_error_msg());
        }

        $decoded = json_decode($content, true);

        if (!is_array($decoded)) {
            throw new Exception('Unexpected payload returned by ChatGPT.');
        }

        $translations = [];

        foreach (array_keys($texts) as $position => $key) {
            $translated = $decoded[(string) $position] ?? null;

            if (is_string($translated) && trim($translated) !== '') {
                $translations[$key] = $translated;
            }
        }

        return $translations;
    }
}
