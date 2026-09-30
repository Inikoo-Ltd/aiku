<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Sep 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\AI\Traits;

use App\Actions\Helpers\AI\ProcessAiTimeSeriesRecords;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use LLPhant\OpenAIConfig;
use OpenAI;
use OpenAI\Client;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Every OpenAI style call goes through OpenRouter when OPENROUTER_API_KEY is set, and straight to
 * OpenAI otherwise. Model names are written the OpenAI way (gpt-4o-mini) and get OpenRouter's
 * provider prefix (openai/gpt-4o-mini) here. Every reply's tokens and cost are saved in ai_usages,
 * under the feature that asked, for the AI dashboard.
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
            ->withHeaders(['X-Title' => 'Aiku'])
            ->withMiddleware($this->aiUsageMiddleware());
    }

    public function aiClient(?string $openAiApiKey = null): Client
    {
        $handler = HandlerStack::create();
        $handler->push($this->aiUsageMiddleware());

        return OpenAI::factory()
            ->withApiKey((string) $this->aiApiKey($openAiApiKey))
            ->withBaseUri($this->aiBaseUrl())
            ->withHttpHeader('X-Title', 'Aiku')
            ->withHttpClient(new GuzzleClient(['handler' => $handler]))
            ->make();
    }

    public function aiLLPhantConfig(string $model, ?string $openAiApiKey = null): OpenAIConfig
    {
        return new OpenAIConfig(
            apiKey: $this->aiApiKey($openAiApiKey),
            url: $this->aiBaseUrl(),
            model: $this->aiModel($model),
            client: $this->aiClient($openAiApiKey),
        );
    }

    public function aiUsageMiddleware(): callable
    {
        $feature  = $this->aiFeature();
        $provider = $this->usesOpenRouter() ? 'openrouter' : 'openai';

        return Middleware::mapResponse(function (ResponseInterface $response) use ($feature, $provider) {
            $this->recordAiUsage($response, $feature, $provider);

            return $response;
        });
    }

    /**
     * The first class in the call stack outside the AI helpers and their parents, e.g.
     * SummarizeChatSession rather than AskToAi, which serves many features.
     */
    public function aiFeature(): string
    {
        foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
            $class = $frame['class'] ?? '';

            if (str_starts_with($class, 'App\\') && !str_starts_with($class, 'App\\Actions\\Helpers\\AI\\') && !is_subclass_of(static::class, $class)) {
                return class_basename($class);
            }
        }

        return 'Other';
    }

    protected function recordAiUsage(ResponseInterface $response, string $feature, string $provider): void
    {
        if ($response->getStatusCode() >= 400 || str_contains($response->getHeaderLine('Content-Type'), 'event-stream')) {
            return;
        }

        try {
            $payload = json_decode((string) $response->getBody(), true);
            $response->getBody()->rewind();

            $usage = Arr::get($payload, 'usage');

            if (!is_array($usage)) {
                return;
            }

            $cost = Arr::get($usage, 'cost');

            if ($cost !== null && Arr::get($usage, 'is_byok')) {
                $cost += (float) Arr::get($usage, 'cost_details.upstream_inference_cost', 0);
            }

            DB::table('ai_usages')->insert([
                'created_at'        => now(),
                'feature'           => $feature,
                'provider'          => $provider,
                'model'             => Arr::get($payload, 'model'),
                'prompt_tokens'     => (int) Arr::get($usage, 'prompt_tokens', Arr::get($usage, 'input_tokens', 0)),
                'completion_tokens' => (int) Arr::get($usage, 'completion_tokens', Arr::get($usage, 'output_tokens', 0)),
                'cost'              => $cost,
            ]);

            ProcessAiTimeSeriesRecords::dispatch(now()->toDateString(), now()->toDateString());
        } catch (Throwable $exception) {
            Log::warning('AI usage not recorded: '.$exception->getMessage());
        }
    }
}
