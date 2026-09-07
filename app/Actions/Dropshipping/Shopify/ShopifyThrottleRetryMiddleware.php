<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 05 Sep 2026 14:10:00 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Shopify;

use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Support\Arr;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Shopify answers a throttled GraphQL call with HTTP 200 and an errors entry coded THROTTLED, and a
 * throttled REST call with 429, so the stock-status based retry of the API library never sees the
 * GraphQL case. Both are retried here after the wait Shopify's own cost extension asks for, at the
 * one point every Shopify call passes through.
 */
class ShopifyThrottleRetryMiddleware
{
    public const string NAME = 'shopify:throttle';

    public const int MAX_ATTEMPTS = 3;

    private const int MAX_WAIT_SECONDS = 5;

    private \Closure $sleep;

    public function __construct(?\Closure $sleep = null)
    {
        $this->sleep = $sleep ?? fn (float $seconds) => usleep((int) ($seconds * 1000000));
    }

    public function __invoke(callable $handler): callable
    {
        return function (RequestInterface $request, array $options) use ($handler): PromiseInterface {
            return $this->attempt($handler, $request, $options, 1);
        };
    }

    private function attempt(callable $handler, RequestInterface $request, array $options, int $attempt): PromiseInterface
    {
        return $handler($request, $options)->then(function (ResponseInterface $response) use ($handler, $request, $options, $attempt) {
            $wait = $this->throttledWait($response);

            if ($wait === null || $attempt >= self::MAX_ATTEMPTS) {
                return $response;
            }

            ($this->sleep)(min($wait, self::MAX_WAIT_SECONDS));

            return $this->attempt($handler, $request, $options, $attempt + 1);
        });
    }

    private function throttledWait(ResponseInterface $response): ?float
    {
        if ($response->getStatusCode() === 429) {
            return max(1.0, (float) $response->getHeaderLine('Retry-After'));
        }

        if ($response->getStatusCode() !== 200) {
            return null;
        }

        $body = $response->getBody();
        if (!$body->isSeekable()) {
            return null;
        }

        $body->rewind();
        $raw = $body->getContents();
        $body->rewind();

        if (!str_contains($raw, 'THROTTLED')) {
            return null;
        }

        $decoded = json_decode($raw, true);

        $throttled = collect(Arr::get($decoded, 'errors', []))
            ->contains(fn ($error) => is_array($error) && Arr::get($error, 'extensions.code') === 'THROTTLED');

        if (!$throttled) {
            return null;
        }

        $status      = Arr::get($decoded, 'extensions.cost.throttleStatus', []);
        $restoreRate = (float) Arr::get($status, 'restoreRate', 0);
        $shortfall   = (float) Arr::get($decoded, 'extensions.cost.requestedQueryCost', 0) - (float) Arr::get($status, 'currentlyAvailable', 0);

        if ($restoreRate > 0 && $shortfall > 0) {
            return max(1.0, ceil($shortfall / $restoreRate));
        }

        return 1.0;
    }
}
