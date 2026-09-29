<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\AI\Traits;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use LLPhant\OpenAIConfig;
use OpenAI;
use OpenAI\Client;

/**
 * Every OpenAI style call goes through OpenRouter when OPENROUTER_API_KEY is set, and straight to
 * OpenAI otherwise. Model names are written the OpenAI way (gpt-4o-mini) and get OpenRouter's
 * provider prefix (openai/gpt-4o-mini) here.
 */
trait WithAIGateway
{
    public function usesOpenRouter(): bool
    {
        return (bool) config('services.openrouter.api_key');
    }

    public function aiBaseUrl(): string
    {
        return $this->usesOpenRouter() ? 'https://openrouter.ai/api/v1' : 'https://api.openai.com/v1';
    }

    public function aiApiKey(?string $openAiApiKey = null): ?string
    {
        if ($this->usesOpenRouter()) {
            return config('services.openrouter.api_key');
        }

        return $openAiApiKey ?: config('services.openai.api_key');
    }

    public function aiModel(string $model): string
    {
        if (!$this->usesOpenRouter() || str_contains($model, '/')) {
            return $model;
        }

        return 'openai/'.$model;
    }

    public function aiRequest(?string $openAiApiKey = null): PendingRequest
    {
        return Http::baseUrl($this->aiBaseUrl())
            ->withToken((string) $this->aiApiKey($openAiApiKey))
            ->withHeaders(['X-Title' => 'Aiku']);
    }

    public function aiClient(): Client
    {
        return OpenAI::factory()
            ->withApiKey((string) $this->aiApiKey())
            ->withBaseUri($this->aiBaseUrl())
            ->withHttpHeader('X-Title', 'Aiku')
            ->make();
    }

    public function aiLLPhantConfig(string $model, ?string $openAiApiKey = null): OpenAIConfig
    {
        return new OpenAIConfig(
            apiKey: $this->aiApiKey($openAiApiKey),
            url: $this->aiBaseUrl(),
            model: $this->aiModel($model),
        );
    }
}
