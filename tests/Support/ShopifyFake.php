<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 05 Sep 2026 14:20:00 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace Tests\Support;

use Gnikyt\BasicShopifyAPI\BasicShopifyAPI;
use Gnikyt\BasicShopifyAPI\Options;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\ResponseSequence;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\RequestInterface;

/**
 * The Shopify API library talks Guzzle directly, so Http::fake never sees it. This hands the
 * library a Guzzle handler that forwards every call through Laravel's Http client, where the
 * replies are faked by GraphQL operation name (or REST path) and stray requests are refused.
 *
 * A reply may be an array (the JSON body), a closure receiving the GraphQL variables, or an
 * Http::response / Http::sequence.
 */
class ShopifyFake
{
    /** @var array<int, array{operation: string, variables: array, url: string}> */
    public static array $requests = [];

    /** @var array<int, string> */
    public static array $stray = [];

    private static array $replies = [];

    private static ?int $fakedFactory = null;

    public static function fake(array $replies): void
    {
        self::$requests = [];
        self::$stray    = [];
        self::$replies  = $replies;

        Http::preventStrayRequests();

        if (self::$fakedFactory === spl_object_id(Http::getFacadeRoot())) {
            return;
        }
        self::$fakedFactory = spl_object_id(Http::getFacadeRoot());

        Http::fake(function (Request $request) {
            $operation = self::operation($request);
            $variables = Arr::get($request->data(), 'variables', []);

            self::$requests[] = ['operation' => $operation, 'variables' => $variables, 'url' => $request->url()];

            if (!array_key_exists($operation, self::$replies)) {
                self::$stray[] = $operation;

                throw new \AssertionError('Unfaked Shopify call: '.$operation.' '.$request->url());
            }

            $reply = self::$replies[$operation];

            if ($reply instanceof \Closure) {
                $reply = $reply($variables, $request);
            }

            if ($reply instanceof ResponseSequence) {
                return $reply($request);
            }

            return is_array($reply) ? Http::response($reply) : $reply;
        });

        config(['shopify-app.auto_migrate_legacy' => false]);
        config(['shopify-app.api_init' => function (Options $options) {
            $options->setGuzzleHandler(self::guzzleHandler());

            return new BasicShopifyAPI($options);
        }]);
    }

    public static function operation(Request $request): string
    {
        $query = Arr::get($request->data(), 'query');

        if (!is_string($query)) {
            return $request->method().' '.Arr::last(explode('/', parse_url($request->url(), PHP_URL_PATH)));
        }

        if (preg_match('/^\s*(?:query|mutation)\s+(\w+)/', $query, $matches)) {
            return $matches[1];
        }

        if (preg_match('/^\s*(?:query|mutation)?\s*\{\s*(\w+)/', $query, $matches)) {
            return $matches[1];
        }

        return 'unknown';
    }

    /**
     * @return array<int, array{operation: string, variables: array, url: string}>
     */
    public static function calls(string $operation): array
    {
        return array_values(array_filter(self::$requests, fn (array $call) => $call['operation'] === $operation));
    }

    public static function graphql(array $data, array $errors = []): array
    {
        return array_filter(['data' => $data, 'errors' => $errors]);
    }

    private static function guzzleHandler(): callable
    {
        return function (RequestInterface $request, array $options): PromiseInterface {
            $headers = array_map(fn (array $values) => implode(', ', $values), $request->getHeaders());
            unset($headers['Content-Length']);

            $response = Http::withHeaders($headers)
                ->withBody((string) $request->getBody(), $request->getHeaderLine('Content-Type') ?: 'application/json')
                ->send($request->getMethod(), (string) $request->getUri());

            return Create::promiseFor($response->toPsrResponse());
        };
    }
}
