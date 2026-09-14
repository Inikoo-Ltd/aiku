<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Services\GoogleAds;

use App\Actions\CRM\Customer\GoogleAds\Traits\WithGoogleAdsAccessToken;
use App\Models\Catalogue\Shop;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * One shop's authenticated conversation with the Google Ads REST API, for both reads and writes.
 *
 * Built per shop rather than per request: an access token costs a round trip to Google's OAuth
 * endpoint and is good for an hour, so a fetch that issues four queries should pay for one, not four.
 * Under Octane the instance must not outlive the request that made it, hence no binding in the
 * container and no static cache of the token.
 */
class GoogleAdsClient
{
    use WithGoogleAdsAccessToken;

    private const string API_VERSION = 'v25';

    private ?string $accessToken = null;

    private function __construct(
        private readonly Shop $shop,
        private readonly string $customerId,
        private readonly string $loginCustomerId
    ) {
    }

    /**
     * Null rather than an exception when the shop is not set up: most callers loop over every shop and
     * want to pass over the ones nobody has connected yet, which is the normal state, not a failure.
     */
    public static function forShop(Shop $shop): ?self
    {
        $customerId = self::onlyDigits((string) Arr::get($shop->settings, 'google_ads.customer_id'));

        if (blank(config('services.google_ads.developer_token')) || $customerId === '') {
            return null;
        }

        if (blank(Arr::get($shop->settings, 'google_ads.refresh_token'))) {
            return null;
        }

        return new self(
            $shop,
            $customerId,
            self::onlyDigits((string) Arr::get($shop->settings, 'google_ads.login_customer_id'))
        );
    }

    /**
     * Why a shop connected to Google Ads still cannot be reached, phrased for the person who has to go
     * and fix it. Returns null when nothing is wrong, so a page can show the reason or show the data.
     */
    public static function unreachableReason(Shop $shop): ?string
    {
        if (blank(config('services.google_ads.developer_token'))) {
            return __('No Google Ads developer token is configured on this installation.');
        }

        if (blank(Arr::get($shop->settings, 'google_ads.refresh_token'))) {
            return __('This shop is not connected to a Google account yet.');
        }

        if (blank(Arr::get($shop->settings, 'google_ads.customer_id'))) {
            return __('This shop has no Google Ads Customer ID set.');
        }

        return null;
    }

    /**
     * Every row matching a GAQL query, following nextPageToken to the end.
     *
     * @return array<int, array>
     * @throws GoogleAdsException
     */
    public function search(string $query): array
    {
        $results   = [];
        $pageToken = null;

        /* No pageSize: v25 rejects the field outright rather than clamping it, and fixes the page at
           10,000 rows itself. nextPageToken is still followed, since an account can exceed that. */
        do {
            $body = ['query' => $query];

            if ($pageToken) {
                $body['pageToken'] = $pageToken;
            }

            $response = $this->post("customers/{$this->customerId}/googleAds:search", $body);

            array_push($results, ...$response->json('results', []));

            $pageToken = $response->json('nextPageToken');
        } while ($pageToken);

        return $results;
    }

    /**
     * Applies write operations to one Google Ads service, e.g. `campaigns` or `campaignBudgets`.
     *
     * Partial failure is off: a half-applied batch leaves Google and Aiku disagreeing about what the
     * account contains, and the nightly fetch would then quietly present the half as the whole truth.
     * Either every operation lands or none does, and the caller hears which one was rejected.
     *
     * `$validateOnly` runs the operations through Google's own validation and applies nothing, which
     * is the only way to find out whether a budget clears the account minimum or an ad passes policy
     * without committing to it first.
     *
     * @param array<int, array> $operations
     * @return array<int, array> the mutate results, in the order the operations were given; empty on a validate-only call
     * @throws GoogleAdsException
     */
    public function mutate(string $service, array $operations, bool $validateOnly = false): array
    {
        if ($operations === []) {
            return [];
        }

        $response = $this->post("customers/{$this->customerId}/{$service}:mutate", [
            'operations'          => array_values($operations),
            'partialFailure'      => false,
            'validateOnly'        => $validateOnly,
            'responseContentType' => 'MUTABLE_RESOURCE',
        ]);

        return $response->json('results', []);
    }

    /**
     * Applies operations across several resource types as one transaction.
     *
     * This is the only way to build something like a campaign safely. A campaign needs a budget, an
     * ad group needs the campaign, an ad needs the ad group, and each is a separate resource: done as
     * four calls, an ad rejected on policy leaves a funded campaign sitting in the account with
     * nothing to show. Here either the whole thing exists or none of it does.
     *
     * Resources refer to each other before they have ids by using a negative one, so
     * `customers/123/campaignBudgets/-1` in a later operation means the budget this same request is
     * creating. The ids must be negative and unique within the request.
     *
     * @param array<int, array> $operations each keyed by its operation type, e.g. `campaignOperation`
     * @return array<int, array>
     * @throws GoogleAdsException
     */
    public function mutateOperations(array $operations, bool $validateOnly = false): array
    {
        if ($operations === []) {
            return [];
        }

        $response = $this->post("customers/{$this->customerId}/googleAds:mutate", [
            'mutateOperations' => array_values($operations),
            'partialFailure'   => false,
            'validateOnly'     => $validateOnly,
        ]);

        return $response->json('mutateOperationResponses', []);
    }

    public function temporaryResource(string $service, int $index): string
    {
        return "customers/{$this->customerId}/{$service}/-{$index}";
    }

    /**
     * @throws GoogleAdsException
     */
    private function post(string $path, array $body): Response
    {
        try {
            $response = Http::withHeaders($this->headers())
                ->timeout(120)
                ->post('https://googleads.googleapis.com/'.self::API_VERSION.'/'.$path, $body);
        } catch (GoogleAdsException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new GoogleAdsException($e->getMessage(), previous: $e);
        }

        if ($response->failed()) {
            throw $this->failure($response);
        }

        return $response;
    }

    /**
     * Google reports a rejected write twice: a human sentence at `error.message`, and the machine
     * reason nested under whichever of a hundred `errorCode` keys applies. The sentence is the one
     * worth showing; the code is taken whatever its key, so a caller can match on it without this
     * class having to know every failure enum Google publishes.
     */
    private function failure(Response $response): GoogleAdsException
    {
        $error    = $response->json('error', []);
        $failures = Arr::get($error, 'details', []);
        $first    = Arr::first(data_get($failures, '*.errors.*')) ?? [];
        $message  = Arr::get($first, 'message') ?: Arr::get($error, 'message') ?: $response->body();

        /* Google's own message is often no more than "The required field was not present", which is
           useless on its own. The field path beside it is the part that says which one, so it is
           carried separately and the caller decides whether a human ever sees it. */
        $path = collect(Arr::get($first, 'location.fieldPathElements', []))
            ->map(fn ($element) => Arr::get($element, 'fieldName').(isset($element['index']) ? "[{$element['index']}]" : ''))
            ->filter()
            ->implode('.');

        return new GoogleAdsException(
            $message,
            Arr::first(Arr::dot(Arr::get($first, 'errorCode', []))),
            $response->status(),
            $failures,
            $path === '' ? null : $path
        );
    }

    private function headers(): array
    {
        $headers = [
            'Authorization'   => 'Bearer '.$this->token(),
            'developer-token' => config('services.google_ads.developer_token'),
        ];

        /* Required whenever the account is reached through a manager account, and rejected outright
           when the account is its own. Google matches it against the account tree, not against us. */
        if ($this->loginCustomerId !== '') {
            $headers['login-customer-id'] = $this->loginCustomerId;
        }

        return $headers;
    }

    /**
     * @throws GoogleAdsException
     */
    private function token(): string
    {
        try {
            return $this->accessToken ??= $this->googleAdsAccessToken($this->shop);
        } catch (Throwable $e) {
            throw new GoogleAdsException($e->getMessage(), previous: $e);
        }
    }

    public function customerId(): string
    {
        return $this->customerId;
    }

    private static function onlyDigits(string $value): string
    {
        return preg_replace('/\D/', '', $value);
    }
}
