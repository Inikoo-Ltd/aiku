<?php

/*
 * Author Louis Perez
 * Created on 23-11-2026-11h-10m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Web\StaticWebsite;

use App\Actions\Web\Website\FetchCruxHistory;
use App\Models\Web\StaticWebpage;
use App\Models\Web\StaticWebsite;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Real user speed of an info website's whole domain, or of one of its pages, from the Chrome UX
 * Report, in the shape the shop websites' report has. crux_records belongs to shop websites, so the
 * 40 weekly windows Google sends are kept in the cache for a day instead of stored. A page Google
 * has no visits for gets no history, as on the shop pages: the domain's figures would read as its own.
 */
class GetStaticWebsiteCruxReport
{
    use AsAction;

    private const int CACHE_HOURS = 24;

    public static function cacheKey(StaticWebsite $staticWebsite, ?StaticWebpage $webpage = null): string
    {
        return "crux-static-website:$staticWebsite->id".($webpage ? ":$webpage->id" : '');
    }

    /**
     * Google fetches the live domain itself, so a local *.test domain is never sent.
     */
    public static function origin(StaticWebsite $staticWebsite): ?string
    {
        if (preg_match('/(^localhost$|\.test$|\.local$|\.localhost$)/i', $staticWebsite->domain)) {
            return null;
        }

        return 'https://www.'.$staticWebsite->domain;
    }

    /**
     * @return array{scope: string|null, url: string|null, history: array<string, array<int, array<string, mixed>>>}
     */
    public function handle(StaticWebsite $staticWebsite, ?StaticWebpage $webpage = null): array
    {
        $origin  = self::origin($staticWebsite);
        $url     = $origin && $webpage ? $origin.$webpage->path : $origin;
        $history = [];

        if ($url && config('app.analytics.google.crux_api_key')) {
            $cacheKey = self::cacheKey($staticWebsite, $webpage);
            $history  = Cache::get($cacheKey);

            if ($history === null) {
                $history = $this->fetch($webpage ? 'url' : 'origin', $url);

                if ($history !== null) {
                    Cache::put($cacheKey, $history, now()->addHours(self::CACHE_HOURS));
                }
            }
        }

        return [
            'scope'   => $history ? ($webpage ? 'page' : 'website') : null,
            'url'     => $url,
            'history' => $history ?? [],
        ];
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>|null null when Google answered with an error, so it is asked again next time
     */
    private function fetch(string $scope, string $url): ?array
    {
        $fetcher = FetchCruxHistory::make();
        $history = [];

        foreach (FetchCruxHistory::FORM_FACTORS as $formFactor => $googleFormFactor) {
            $record = $fetcher->query($scope, $url, $googleFormFactor);

            if (is_string($record)) {
                return null;
            }

            if ($record === null) {
                if ($formFactor === 'all') {
                    return [];
                }

                continue;
            }

            $periods = $fetcher->periods($record);

            if ($periods) {
                $history[$formFactor] = $periods;
            }
        }

        return $history;
    }
}
