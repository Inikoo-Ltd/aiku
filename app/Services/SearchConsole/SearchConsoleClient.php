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
use Google\Service\Webmasters;
use Google\Service\Webmasters\ApiDataRow;
use Google\Service\Webmasters\SearchAnalyticsQueryRequest;
use GuzzleHttp\Exception\ConnectException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Throwable;

class SearchConsoleClient
{
    public const string PROVIDER = 'google_search_console';

    public const int ROW_LIMIT = 25000;

    private function __construct(
        private readonly Website $website,
        private readonly Webmasters $service,
        private readonly string $siteUrl
    ) {
    }

    public static function forWebsite(Website $website): ?self
    {
        $credentials = self::credentials($website);

        if (!$credentials) {
            return null;
        }

        $service = self::service($credentials);
        $siteUrl = Arr::get($website->data, 'gcp.siteUrl') ?: self::findSiteUrl($website, $service);

        if (!$siteUrl) {
            return null;
        }

        return new self($website, $service, $siteUrl);
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

    private static function findSiteUrl(Website $website, Webmasters $service): ?string
    {
        $domain     = Str::of($website->domain)->lower()->after('www.')->toString();
        $candidates = [
            "sc-domain:$domain",
            "https://www.$domain/",
            "https://$domain/",
            "http://www.$domain/",
            "http://$domain/",
        ];

        $siteEntries = self::logged($website, 'sites.list', fn () => $service->sites->listSites()->getSiteEntry() ?? []);
        $siteUrls    = Arr::pluck($siteEntries, 'siteUrl');
        $siteUrl     = Arr::first($candidates, fn (string $candidate) => in_array($candidate, $siteUrls, true));

        if ($siteUrl) {
            $websiteData = $website->data;
            data_set($websiteData, 'gcp.siteUrl', $siteUrl);
            $website->update(['data' => $websiteData]);
        }

        return $siteUrl;
    }

    private static function logged(Website $website, string $endpoint, callable $call): array
    {
        $startedAt = hrtime(true);

        try {
            $result = retry(3, $call, 2000, fn (Throwable $e) => $e instanceof ConnectException);
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
