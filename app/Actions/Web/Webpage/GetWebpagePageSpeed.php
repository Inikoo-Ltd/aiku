<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 10 Sep 2026 10:00:00 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Webpage;

use App\Models\Web\Webpage;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Lorisleiva\Actions\Concerns\AsAction;
use Sentry;
use Throwable;

class GetWebpagePageSpeed
{
    use AsAction;

    private const ENDPOINT = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';

    private const CATEGORIES = [
        'performance'    => 'Performance',
        'accessibility'  => 'Accessibility',
        'best-practices' => 'Best practices',
        'seo'            => 'SEO',
    ];

    private const LAB_AUDITS = [
        'first-contentful-paint'   => 'First Contentful Paint',
        'largest-contentful-paint' => 'Largest Contentful Paint',
        'total-blocking-time'      => 'Total Blocking Time',
        'cumulative-layout-shift'  => 'Cumulative Layout Shift',
        'speed-index'              => 'Speed Index',
    ];

    private const FIELD_METRICS = [
        'LARGEST_CONTENTFUL_PAINT_MS'    => 'Largest Contentful Paint',
        'INTERACTION_TO_NEXT_PAINT'      => 'Interaction to Next Paint',
        'CUMULATIVE_LAYOUT_SHIFT_SCORE'  => 'Cumulative Layout Shift',
        'FIRST_CONTENTFUL_PAINT_MS'      => 'First Contentful Paint',
        'EXPERIMENTAL_TIME_TO_FIRST_BYTE' => 'Time to First Byte',
    ];

    /**
     * @return array{url: string, strategy: string, fetched_at: string, scores: array, lab: array, field: array, overall_rating: string|null}|array{error: string}
     */
    public function handle(Webpage $webpage, string $strategy = 'mobile'): array
    {
        $url = $this->publiclyReachableUrl($webpage);

        if (!$url) {
            return ['error' => __('This webpage has no publicly reachable URL to analyse')];
        }

        $cacheKey = "webpage-pagespeed:$webpage->id:$strategy";
        $cached   = cache()->get($cacheKey);

        if ($cached) {
            return $cached;
        }

        $result = $this->fetch($url, $strategy);

        if (!Arr::get($result, 'error')) {
            cache()->put($cacheKey, $result, now()->addHours(6));
        }

        return $result;
    }

    /**
     * PageSpeed Insights runs from Google's servers, so it can only measure the live canonical
     * page. Locally getUrl() resolves to an unreachable *.test domain, hence the canonical_url
     * of the real published page takes precedence.
     */
    private function publiclyReachableUrl(Webpage $webpage): ?string
    {
        $url  = $webpage->canonical_url ?: $webpage->getUrl();
        $host = parse_url($url, PHP_URL_HOST);

        if (!$host || preg_match('/(^localhost$|\.test$|\.local$|\.localhost$)/i', $host)) {
            return null;
        }

        return $url;
    }

    private function fetch(string $url, string $strategy): array
    {
        try {
            $response = Http::timeout(90)
                ->retry(2, 2000, fn (Throwable $exception) => $exception instanceof ConnectionException, false)
                ->get(self::ENDPOINT, array_filter([
                    'url'      => $url,
                    'strategy' => $strategy,
                    'category' => array_keys(self::CATEGORIES),
                    'key'      => config('app.analytics.google.pagespeed_api_key'),
                ]));
        } catch (Throwable $e) {
            Sentry::captureException($e);

            return ['error' => $e->getMessage()];
        }

        if ($response->failed()) {
            return [
                'error' => Arr::get($response->json(), 'error.message', __('PageSpeed Insights request failed')),
            ];
        }

        $payload = $response->json();

        return [
            'url'            => Arr::get($payload, 'lighthouseResult.finalUrl', $url),
            'strategy'       => $strategy,
            'fetched_at'     => Arr::get($payload, 'analysisUTCTimestamp', now()->toIso8601String()),
            'scores'         => $this->scores($payload),
            'lab'            => $this->labMetrics($payload),
            'field'          => $this->fieldMetrics($payload),
            'overall_rating' => Arr::get($payload, 'loadingExperience.overall_category'),
        ];
    }

    private function scores(array $payload): array
    {
        $scores = [];

        foreach (self::CATEGORIES as $key => $label) {
            $score = Arr::get($payload, "lighthouseResult.categories.$key.score");

            if ($score === null) {
                continue;
            }

            $scores[] = [
                'key'    => $key,
                'label'  => $label,
                'score'  => (int)round($score * 100),
                'rating' => $this->ratingFromScore($score),
            ];
        }

        return $scores;
    }

    private function labMetrics(array $payload): array
    {
        $metrics = [];

        foreach (self::LAB_AUDITS as $key => $label) {
            $audit = Arr::get($payload, "lighthouseResult.audits.$key");

            if (!$audit) {
                continue;
            }

            $metrics[] = [
                'key'     => $key,
                'label'   => $label,
                'value'   => Arr::get($audit, 'numericValue'),
                'display' => Arr::get($audit, 'displayValue'),
                'rating'  => $this->ratingFromScore(Arr::get($audit, 'score')),
            ];
        }

        return $metrics;
    }

    private function fieldMetrics(array $payload): array
    {
        $metrics = [];

        foreach (self::FIELD_METRICS as $key => $label) {
            $metric = Arr::get($payload, "loadingExperience.metrics.$key");

            if (!$metric) {
                continue;
            }

            $distributions = array_map(
                fn ($bucket) => (int)round(Arr::get($bucket, 'proportion', 0) * 100),
                Arr::get($metric, 'distributions', [])
            );

            $metrics[] = [
                'key'           => $key,
                'label'         => $label,
                'percentile'    => Arr::get($metric, 'percentile'),
                'display'       => $this->displayFieldValue($key, Arr::get($metric, 'percentile')),
                'rating'        => strtolower(Arr::get($metric, 'category', '')),
                'distributions' => $distributions,
            ];
        }

        return $metrics;
    }

    private function displayFieldValue(string $key, int|float|null $percentile): ?string
    {
        if ($percentile === null) {
            return null;
        }

        return match ($key) {
            'CUMULATIVE_LAYOUT_SHIFT_SCORE' => number_format($percentile / 100, 2),
            'INTERACTION_TO_NEXT_PAINT' => $percentile.' ms',
            default => number_format($percentile / 1000, 1).' s',
        };
    }

    private function ratingFromScore(int|float|null $score): ?string
    {
        return match (true) {
            $score === null => null,
            $score >= 0.9 => 'fast',
            $score >= 0.5 => 'average',
            default => 'slow',
        };
    }
}
