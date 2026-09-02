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
use Sentry;
use Throwable;
use TikToken\Encoder;
use VildanBina\LaravelAutoTranslation\Contracts\TranslationDriver;

class ChatGPT5Driver implements TranslationDriver
{
    use WithAICreditErrorHandler;

    private const BUFFER_FACTOR = 2;

    // Below this a retry is not worth the call; the strings just stay English.
    private const MIN_CHUNK = 4;

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

        foreach ($chunks as $chunk) {
            $translations += $this->translateChunk($chunk, $sourceLang, $targetLang);
        }

        if (! empty($currentChunk)) {
            $chunkResult = $this->sendTranslationRequest($currentChunk, $sourceLang, $targetLang);
            $translations += is_array($chunkResult) ? $chunkResult : [];
        }

        return $translations;
    }

    /**
     * A reply is rejected whenever the model drops an entry, which it does for anything it
     * reads as untranslatable ("CSV", "B2B"). Discarding 800 strings over one acronym
     * loses almost everything, so halve the batch and retry: the offending entry ends up
     * alone in a tiny chunk and only it is lost.
     *
     * @return array<string, string>
     */
    private function translateChunk(array $chunk, string $sourceLang, string $targetLang): array
    {
        try {
            return $this->sendTranslationRequest($chunk, $sourceLang, $targetLang);
        } catch (AICreditException $e) {
            throw $e;
        } catch (Throwable $e) {
            if (count($chunk) <= self::MIN_CHUNK) {
                Sentry::captureMessage('Translation chunk skipped: '.$e->getMessage());

                return [];
            }
        }

        $halves = array_chunk($chunk, (int) ceil(count($chunk) / 2), true);
        $translations = [];

        foreach ($halves as $half) {
            $translations += $this->translateChunk($half, $sourceLang, $targetLang);
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

        // Multi-line source strings come back with the newline written raw inside the JSON
        // string rather than escaped, which is a parse error. They are never meaningful
        // in a UI label, so fold them to spaces instead of losing the whole batch.
        // No /u modifier: it makes preg_replace return null on malformed UTF-8, which fed
        // the unfixed string straight back to the validator. Control characters are single
        // bytes below 0x20 and UTF-8 continuation bytes are all >= 0x80, so byte matching
        // cannot damage the Devanagari or Han text around them.
        $content = preg_replace('/[\x00-\x1F]+/', ' ', $content) ?? $content;

        if (! json_validate($content)) {
            throw new Exception('Invalid JSON returned by ChatGPT: '.json_last_error_msg());
        }

        $decoded = json_decode($content, true);

        if (!is_array($decoded)) {
            throw new Exception('Unexpected payload returned by ChatGPT.');
        }

        // The model sometimes drops entries it thinks need no translation AND renumbers
        // what is left, which silently shifts every later value onto the wrong key. A
        // batch is only trustworthy if every index sent comes back; otherwise discard it
        // and let those strings fall back to English.
        $expected = count($texts);

        for ($position = 0; $position < $expected; $position++) {
            if (!is_string($decoded[(string) $position] ?? null)) {
                throw new Exception("ChatGPT returned {$position} of {$expected} indexed entries - discarding batch rather than misaligning it.");
            }
        }

        $translations = [];

        foreach (array_keys($texts) as $position => $key) {
            $translated = $decoded[(string) $position];

            if (trim($translated) !== '') {
                $translations[$key] = $translated;
            }
        }

        return $translations;
    }
}
