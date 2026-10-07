<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Sep 2026 23:40:00 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website;

use App\Models\Web\CruxRecord;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * Real user speed from the Chrome UX Report: 28 day windows of real Chrome visits, one per week.
 * Google only reports a page with enough visits, so most pages come back without data and are
 * judged by the whole website instead.
 */
class FetchCruxHistory
{
    use AsAction;

    public string $jobQueue = 'cache-warming';

    private const string ENDPOINT = 'https://chromeuxreport.googleapis.com/v1/records:queryHistoryRecord';

    private const int CHECKED_TTL_HOURS = 24;

    public const array FORM_FACTORS = [
        'all'     => null,
        'desktop' => 'DESKTOP',
        'phone'   => 'PHONE',
    ];

    private const array METRICS = [
        'lcp'  => ['largest_contentful_paint'],
        'inp'  => ['interaction_to_next_paint'],
        'cls'  => ['cumulative_layout_shift'],
        'fcp'  => ['first_contentful_paint'],
        'ttfb' => ['time_to_first_byte', 'experimental_time_to_first_byte'],
    ];

    public static function checkedKey(Website $website, ?Webpage $webpage): string
    {
        return "crux-checked:$website->id:".($webpage?->id ?? 'origin');
    }

    /**
     * Google fetches the live canonical page itself, so a local *.test address is never sent.
     */
    public static function publicUrl(?Webpage $webpage): ?string
    {
        if (!$webpage) {
            return null;
        }

        $url  = $webpage->canonical_url ?: $webpage->getUrl();
        $host = parse_url($url, PHP_URL_HOST);

        if (!$host || preg_match('/(^localhost$|\.test$|\.local$|\.localhost$)/i', $host)) {
            return null;
        }

        return $url;
    }

    public static function origin(Website $website): ?string
    {
        $url = self::publicUrl($website->storefront);

        return $url ? parse_url($url, PHP_URL_SCHEME).'://'.parse_url($url, PHP_URL_HOST) : null;
    }

    /**
     * @return string stored, no_data, checked_recently, unavailable or the Google error message
     */
    public function handle(Website $website, ?Webpage $webpage = null): string
    {
        $url = $webpage ? self::publicUrl($webpage) : self::origin($website);

        if (!$url || !config('app.analytics.google.crux_api_key')) {
            return 'unavailable';
        }

        if (!cache()->add(self::checkedKey($website, $webpage), true, now()->addHours(self::CHECKED_TTL_HOURS))) {
            return 'checked_recently';
        }

        $rows = [];

        foreach (self::FORM_FACTORS as $formFactor => $googleFormFactor) {
            $record = $this->query($webpage ? 'url' : 'origin', $url, $googleFormFactor);

            if (is_string($record)) {
                cache()->forget(self::checkedKey($website, $webpage));

                return $record;
            }

            if ($record === null) {
                if ($formFactor === 'all') {
                    return 'no_data';
                }

                continue;
            }

            $rows = [...$rows, ...$this->rows($website, $webpage, $url, $formFactor, $record)];
        }

        CruxRecord::upsert(
            $rows,
            ['website_id', 'webpage_id', 'form_factor', 'period_end'],
            ['url', 'period_start', 'lcp_p75', 'inp_p75', 'cls_p75', 'fcp_p75', 'ttfb_p75', 'histograms', 'updated_at']
        );

        return 'stored';
    }

    /**
     * @return array<string, mixed>|string|null the record, the error message, or null when Google has no data
     */
    private function query(string $scope, string $url, ?string $formFactor): array|string|null
    {
        try {
            $response = Http::timeout(15)
                ->retry(2, 1000, fn (Throwable $exception) => $exception instanceof ConnectionException, false)
                ->post(self::ENDPOINT.'?key='.config('app.analytics.google.crux_api_key'), array_filter([
                    $scope                  => $url,
                    'formFactor'            => $formFactor,
                    'collectionPeriodCount' => 40,
                ]));
        } catch (Throwable $e) {
            return $e->getMessage();
        }

        if ($response->status() === 404) {
            return null;
        }

        if ($response->failed()) {
            return Arr::get($response->json(), 'error.message', 'Chrome UX Report request failed');
        }

        return $response->json('record');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rows(Website $website, ?Webpage $webpage, string $url, string $formFactor, array $record): array
    {
        $rows = [];

        foreach (Arr::get($record, 'collectionPeriods', []) as $index => $period) {
            $row = [
                'website_id'   => $website->id,
                'webpage_id'   => $webpage?->id,
                'url'          => $url,
                'form_factor'  => $formFactor,
                'period_start' => $this->date(Arr::get($period, 'firstDate')),
                'period_end'   => $this->date(Arr::get($period, 'lastDate')),
                'created_at'   => now(),
                'updated_at'   => now(),
            ];

            $histograms = [];

            foreach (self::METRICS as $key => $names) {
                $metric = collect($names)->map(fn ($name) => Arr::get($record, "metrics.$name"))->filter()->first();

                $row[$key.'_p75'] = $this->number(Arr::get($metric, "percentilesTimeseries.p75s.$index"), $key);

                $densities = collect(Arr::get($metric, 'histogramTimeseries', []))->map(fn ($bin) => Arr::get($bin, "densities.$index"));

                if ($densities->isNotEmpty() && $densities->every(fn ($density) => is_numeric($density))) {
                    $histograms[$key] = $densities->map(fn ($density) => round((float)$density, 4))->all();
                }
            }

            if (collect(array_keys(self::METRICS))->every(fn ($key) => $row[$key.'_p75'] === null)) {
                continue;
            }

            $rows[] = $row + ['histograms' => json_encode((object)$histograms)];
        }

        return $rows;
    }

    private function number(mixed $value, string $metric): int|float|null
    {
        if (!is_numeric($value)) {
            return null;
        }

        return $metric === 'cls' ? round((float)$value, 3) : (int)round((float)$value);
    }

    private function date(array $date): string
    {
        return Carbon::create($date['year'], $date['month'], $date['day'])->toDateString();
    }
}
