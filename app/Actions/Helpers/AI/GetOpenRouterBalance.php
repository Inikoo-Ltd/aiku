<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Sep 2026 12:20:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\AI;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * What is left to spend on OpenRouter: the account credit and the key's own limit, whichever runs
 * out first stops every AI call. Null without a key or when OpenRouter does not answer.
 */
class GetOpenRouterBalance
{
    use AsAction;

    /**
     * @return array{credits_total: float, credits_used: float, credits_left: float, key_limit: float|null, key_limit_remaining: float|null, key_limit_reset: string|null, left: float, low_credit_alert: float, is_low: bool}|null
     */
    public function handle(bool $fresh = false): ?array
    {
        $apiKey = config('services.openrouter.api_key');

        if (!$apiKey) {
            return null;
        }

        if ($fresh) {
            Cache::forget('ai:openrouter_balance');
        }

        return Cache::remember('ai:openrouter_balance', now()->addMinutes(5), function () use ($apiKey) {
            try {
                $request = Http::withToken($apiKey)->baseUrl('https://openrouter.ai/api/v1')->connectTimeout(5)->timeout(10);
                $key     = $request->get('key')->throw()->json('data');
                $credits = $request->get('credits')->throw()->json('data');
            } catch (Throwable $exception) {
                Log::warning('OpenRouter balance: '.$exception->getMessage());

                return null;
            }

            $creditsLeft       = (float) $credits['total_credits'] - (float) $credits['total_usage'];
            $keyLimitRemaining = isset($key['limit_remaining']) ? (float) $key['limit_remaining'] : null;
            $left              = $keyLimitRemaining === null ? $creditsLeft : min($creditsLeft, $keyLimitRemaining);
            $lowCreditAlert    = (float) config('services.openrouter.low_credit_alert');

            return [
                'credits_total'       => (float) $credits['total_credits'],
                'credits_used'        => (float) $credits['total_usage'],
                'credits_left'        => $creditsLeft,
                'key_limit'           => isset($key['limit']) ? (float) $key['limit'] : null,
                'key_limit_remaining' => $keyLimitRemaining,
                'key_limit_reset'     => $key['limit_reset'] ?? null,
                'left'                => $left,
                'low_credit_alert'    => $lowCreditAlert,
                'is_low'              => $left < $lowCreditAlert,
            ];
        });
    }
}
