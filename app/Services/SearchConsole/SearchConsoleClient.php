<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Services\SearchConsole;

use App\Models\Web\SeoApiRequest;
use App\Models\Web\Website;
use Google\Client;
use Google\Service\Exception as GoogleServiceException;
use Google\Service\Webmasters;
use Google\Service\Webmasters\ApiDataRow;
use Google\Service\Webmasters\SearchAnalyticsQueryRequest;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Throwable;

class SearchConsoleClient
{
    public const string PROVIDER = 'google_search_console';

    public const int ROW_LIMIT = 25000;

    /**
     * Google allows 1,200 search analytics queries a minute per site and per user, the service account here,
     * which every website using these credentials shares (https://developers.google.com/webmaster-tools/limits).
     * Counting a whole minute let a run send its 1,200 in the first 30 seconds and get 403 "QPS quota exceeded",
     * so queries are spread per second at half the allowance.
     */
    public const int CALLS_PER_SECOND = 10;

    private function __construct(
        private readonly Website $website,
        private readonly Webmasters $service,
        private readonly string $siteUrl,
        private readonly string $account
    ) {
    }

    public static function forWebsite(Website $website): ?self
    {
        $credentials = self::credentials($website);

        if (!$credentials) {
            return null;
        }

        $service = self::service($credentials);
        $account = (string) ($credentials['client_email'] ?? $website->group_id);
        $siteUrl = Arr::get($website->data, 'gcp.siteUrl') ?: self::findSiteUrl($website, $service, $account);

        if (!$siteUrl) {
            return null;
        }

        return new self($website, $service, $siteUrl, $account);
    }

    public static function serviceAccountEmail(Website $website): ?string
    {
        return Arr::get(self::credentials($website) ?? [], 'client_email');
    }

    public function siteUrl(): string
    {
        return $this->siteUrl;
    }

    /**
     * @param  array<int, string>  $dimensions
     * @return array<int, array{keys: array<int, string>, clicks: int, impressions: int, position: float}>
     */
    public function rowsForDay(array $dimensions, string $date): array
    {
        $rows     = [];
        $startRow = 0;

        do {
            $page     = $this->query($dimensions, $date, $startRow);
            $rows     = array_merge($rows, $page);
            $startRow += self::ROW_LIMIT;
        } while (count($page) === self::ROW_LIMIT);

        return $rows;
    }

    /**
     * @param  array<int, string>  $dimensions
     * @return array<int, array{keys: array<int, string>, clicks: int, impressions: int, position: float}>
     */
    private function query(array $dimensions, string $date, int $startRow): array
    {
        $request             = new SearchAnalyticsQueryRequest();
        $request->startDate  = $date;
        $request->endDate    = $date;
        $request->dimensions = $dimensions;
        $request->rowLimit   = self::ROW_LIMIT;
        $request->startRow   = $startRow;
        $request->searchType = 'web';
        $request->dataState  = 'final';

        $rows = self::logged(
            $this->website,
            $this->account,
            'searchanalytics.query:'.implode(',', $dimensions),
            fn () => $this->service->searchanalytics->query($this->siteUrl, $request)->getRows() ?? []
        );

        return array_map(fn (ApiDataRow $row) => [
            'keys'        => $row->getKeys(),
            'clicks'      => (int) $row->getClicks(),
            'impressions' => (int) $row->getImpressions(),
            'position'    => round((float) $row->getPosition(), 2),
        ], $rows);
    }

    private static function credentials(Website $website): ?array
    {
        $encodedSecret = Arr::get($website->group->settings, 'gcp.oauthClientSecret') ?: config('app.analytics.google.client_oauth_secret');

        if (!$encodedSecret) {
            return null;
        }

        return json_decode(base64_decode($encodedSecret), true) ?: null;
    }

    private static function service(array $credentials): Webmasters
    {
        $client = new Client();
        $client->setAuthConfig($credentials);
        $client->addScope(Webmasters::WEBMASTERS_READONLY);

        return new Webmasters($client);
    }

    private static function findSiteUrl(Website $website, Webmasters $service, string $account): ?string
    {
        $domain     = Str::of($website->domain)->lower()->after('www.')->toString();
        $candidates = [
            "sc-domain:$domain",
            "https://www.$domain/",
            "https://$domain/",
            "http://www.$domain/",
            "http://$domain/",
        ];

        $siteEntries = self::logged($website, $account, 'sites.list', fn () => $service->sites->listSites()->getSiteEntry() ?? []);
        $siteUrls    = Arr::pluck($siteEntries, 'siteUrl');
        $siteUrl     = Arr::first($candidates, fn (string $candidate) => in_array($candidate, $siteUrls, true));

        if ($siteUrl) {
            $websiteData = $website->data;
            data_set($websiteData, 'gcp.siteUrl', $siteUrl);
            $website->update(['data' => $websiteData]);
        }

        return $siteUrl;
    }

    public static function isWorthRetrying(Throwable $e): bool
    {
        return $e instanceof ConnectException || self::isQuotaError($e);
    }

    /**
     * Google answers a burst of search analytics queries with a 403 quotaExceeded or a 429 that clears within
     * seconds, and a used up daily allowance with the same answer that only clears the next day.
     */
    public static function isQuotaError(Throwable $e): bool
    {
        if (!$e instanceof GoogleServiceException) {
            return false;
        }

        $reasons = Arr::pluck($e->getErrors() ?? [], 'reason');

        return $e->getCode() === 429 || array_intersect($reasons, ['quotaExceeded', 'rateLimitExceeded', 'userRateLimitExceeded']) !== [];
    }

    private static function logged(Website $website, string $account, string $endpoint, callable $call): array
    {
        $startedAt = hrtime(true);

        try {
            $result = retry(
                [2000, 10000, 30000],
                fn () => Redis::throttle('search-console:'.$account)
                    ->allow(self::CALLS_PER_SECOND)->every(1)->block(600)
                    ->then($call),
                null,
                fn (Throwable $e) => self::isWorthRetrying($e)
            );
        } catch (Throwable $e) {
            self::log($website, $endpoint, false, 0, $startedAt, $e->getMessage());

            throw $e;
        }

        self::log($website, $endpoint, true, count($result), $startedAt);

        return $result;
    }

    private static function log(Website $website, string $endpoint, bool $isSuccess, int $rows, int $startedAt, ?string $error = null): void
    {
        SeoApiRequest::create([
            'provider'    => self::PROVIDER,
            'endpoint'    => $endpoint,
            'website_id'  => $website->id,
            'is_success'  => $isSuccess,
            'rows'        => $rows,
            'duration_ms' => intdiv(hrtime(true) - $startedAt, 1_000_000),
            'error'       => $error ? Str::limit($error, 2000) : null,
        ]);
    }
}
