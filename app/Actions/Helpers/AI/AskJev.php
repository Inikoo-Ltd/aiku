<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 29 Sep 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\AI;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * Typed questions to TypeSafe's Jev through OpenRouter's Decisions API. Jev does not write text:
 * it answers a yes/no question with a probability (noul), picks one of the options given
 * (choice) or places the state on an ordered rubric (score), always with calibrated
 * probabilities, for a fraction of the cost of a chat model. Null when there is no key or no
 * answer.
 */
class AskJev
{
    use AsAction;

    /**
     * Several questions about one state in a single call.
     *
     * @param  string|array<mixed>  $state
     * @param  array<string, array{type: 'noul'|'choice'|'score', instructions: string, criteria: array<string|int, string>}>  $questions
     * @return array<string, array<string, mixed>>|null answers keyed like the questions
     */
    public function handle(string|array $state, array $questions): ?array
    {
        $apiKey = config('services.openrouter.api_key');

        if (! $apiKey) {
            return null;
        }

        try {
            $response = Http::withToken($apiKey)
                ->connectTimeout(10)
                ->timeout(20)
                ->retry(2, 500, fn (Throwable $exception) => $exception instanceof ConnectionException)
                ->withHeaders(['X-Title' => 'Aiku'])
                ->post('https://openrouter.ai/api/alpha/decisions', [
                    'model'     => config('services.openrouter.decision_model'),
                    'state'     => $state,
                    'questions' => $questions,
                ])
                ->throw();
        } catch (Throwable $exception) {
            Log::error('AskJev: '.$exception->getMessage());

            return null;
        }

        $answers = $response->json('answers');

        return is_array($answers) ? $answers : null;
    }

    /**
     * @param  string|array<mixed>  $state
     * @return float|null probability from 0 (no) to 1 (yes)
     */
    public function noul(string|array $state, string $instructions, string $whenTrue, string $whenFalse): ?float
    {
        $probability = Arr::get($this->handle($state, ['answer' => [
            'type'         => 'noul',
            'instructions' => $instructions,
            'criteria'     => ['true' => $whenTrue, 'false' => $whenFalse],
        ]]), 'answer.noul');

        return is_numeric($probability) ? (float) $probability : null;
    }

    /**
     * @param  string|array<mixed>  $state
     * @param  array<string, string>  $options  option => what it means, at most 255
     * @return array{choice: string, probabilities: array<string, float>, confidence?: float}|null
     */
    public function choice(string|array $state, string $instructions, array $options): ?array
    {
        $answer = Arr::get($this->handle($state, ['answer' => [
            'type'         => 'choice',
            'instructions' => $instructions,
            'criteria'     => $options,
        ]]), 'answer');

        return isset($answer['choice']) ? Arr::only($answer, ['choice', 'probabilities', 'confidence']) : null;
    }

    /**
     * @param  string|array<mixed>  $state
     * @param  array<int, string>  $rubric  ordered levels, lowest first
     * @return array{score: float, probabilities: array<int, float>, confidence?: float}|null
     */
    public function score(string|array $state, string $instructions, array $rubric): ?array
    {
        $answer = Arr::get($this->handle($state, ['answer' => [
            'type'         => 'score',
            'instructions' => $instructions,
            'criteria'     => array_values($rubric),
        ]]), 'answer');

        return isset($answer['score']) ? Arr::only($answer, ['score', 'probabilities', 'confidence']) : null;
    }
}
