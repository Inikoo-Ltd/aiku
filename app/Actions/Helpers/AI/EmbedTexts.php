<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 23:50:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\AI;

use App\Actions\Helpers\AI\Traits\WithAIGateway;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * Embeddings through OpenRouter, one vector per text in the order given. Null when there is no
 * key or the call fails.
 */
class EmbedTexts
{
    use AsAction;
    use WithAIGateway;

    /**
     * Short and with one retry by default, for a reply being written; the nightly batch waits
     * longer and backs off when rate limited.
     *
     * @param  array<int, string>  $texts
     * @return array<int, array<int, float>>|null
     */
    public function handle(array $texts, bool $batch = false): ?array
    {
        if (!$texts || !$this->usesOpenRouter()) {
            return null;
        }

        try {
            $data = $this->aiRequest()
                ->connectTimeout($batch ? 10 : 3)
                ->timeout($batch ? 60 : 8)
                ->retry($batch ? 5 : 2, fn (int $attempt) => $batch ? $attempt * 3000 : 300, fn (Throwable $exception) => $exception instanceof ConnectionException || ($exception instanceof RequestException && $exception->response->status() === 429))
                ->post('embeddings', ['model' => config('services.openrouter.embedding_model'), 'input' => array_values($texts)])
                ->throw()
                ->json('data');
        } catch (Throwable $exception) {
            Log::error('EmbedTexts: '.$exception->getMessage());

            return null;
        }

        return is_array($data) ? collect($data)->sortBy('index')->pluck('embedding')->values()->all() : null;
    }
}
