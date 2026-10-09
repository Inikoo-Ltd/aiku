<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo;

use App\Models\Web\SeoApiRequest;
use App\Services\DataForSeo\DataForSeoClient;
use App\Services\DataForSeo\DataForSeoException;
use App\Services\SeoApi\SeoApiBudget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * What the SEO APIs cost this month and before, from `seo_api_requests`, for the SEO API usage page.
 */
class GetSeoApiUsage
{
    use AsObject;

    public const int WARNING_SHARE = 80;

    private const int HISTORY_MONTHS = 6;

    private const int LATEST_ERRORS = 20;

    private const int BALANCE_CACHE_MINUTES = 10;

    /**
     * The feature an endpoint belongs to, first match wins. Labs `ranked_keywords` is used by both
     * keyword research (a URL) and competitor research; it is counted as competitor research.
     */
    private const array FEATURES = [
        'content_suggestions'                     => 'Content help',
        'ai_optimization/'                        => 'AI visibility',
        'serp/'                                   => 'Rank tracking',
        'backlinks/domain_intersection'           => 'Backlink gap',
        'backlinks/'                              => 'Backlinks',
        'dataforseo_labs/google/keyword_overview' => 'Keyword volumes',
        'dataforseo_labs/google/keyword_'         => 'Keyword research',
        'dataforseo_labs/google/historical_bulk_' => 'Competitor traffic',
        'dataforseo_labs/google/'                 => 'Competitor research',
        'dataforseo_labs/locations'               => 'Location lists',
        'appendix/'                               => 'Account balance',
        'searchanalytics'                         => 'Search Console',
        'sites.list'                              => 'Search Console',
    ];

    public function handle(?Carbon $month = null): array
    {
        $month = ($month ?? now())->copy()->startOfMonth();
        $end   = $month->copy()->addMonth();

        $spend     = SeoApiBudget::monthSpend($month);
        $budget    = SeoApiBudget::monthlyBudget();
        $isCurrent = $month->isSameMonth(now());
        $daysGone  = $isCurrent ? max(1, now()->day) : $month->daysInMonth;

        $requests = SeoApiRequest::query()
            ->where('created_at', '>=', $month)
            ->where('created_at', '<', $end)
            ->toBase()
            ->groupBy('provider', 'endpoint')
            ->select('provider', 'endpoint')
            ->selectRaw('COUNT(*) AS requests')
            ->selectRaw('COUNT(*) FILTER (WHERE NOT is_success) AS errors')
            ->selectRaw('COALESCE(SUM(cost), 0) AS cost')
            ->get();

        $features = $requests
            ->groupBy(fn ($row) => $row->provider.'|'.self::feature($row->endpoint))
            ->map(fn ($rows, $key) => [
                'provider' => Str::before($key, '|'),
                'feature'  => Str::after($key, '|'),
                'requests' => (int) $rows->sum('requests'),
                'errors'   => (int) $rows->sum('errors'),
                'cost'     => round((float) $rows->sum('cost'), 4),
            ])
            ->sortByDesc('cost')
            ->values()
            ->all();

        $daily = SeoApiRequest::query()
            ->where('created_at', '>=', $month)
            ->where('created_at', '<', $end)
            ->toBase()
            ->groupBy(DB::raw('created_at::date'))
            ->selectRaw('created_at::date AS day')
            ->selectRaw('COALESCE(SUM(cost), 0) AS cost')
            ->pluck('cost', 'day');

        return [
            'month'     => $month->toDateString(),
            'is_current' => $isCurrent,
            'spend'     => round($spend, 2),
            'budget'    => $budget,
            'left'      => round(max(0, $budget - $spend), 2),
            'projected' => $isCurrent ? round($spend / $daysGone * $month->daysInMonth, 2) : round($spend, 2),
            'share'     => $budget > 0 ? (int) round($spend / $budget * 100) : null,
            'warning'   => self::WARNING_SHARE,
            'requests'  => (int) $requests->sum('requests'),
            'errors'    => (int) $requests->sum('errors'),
            'features'  => $features,
            'daily'     => collect(range(1, $month->daysInMonth))
                ->map(fn (int $day) => $month->copy()->day($day)->toDateString())
                ->map(fn (string $day) => ['day' => $day, 'cost' => round((float) ($daily[$day] ?? 0), 4)])
                ->all(),
            'history'   => collect(range(1, self::HISTORY_MONTHS))
                ->map(fn (int $monthsBack) => $month->copy()->subMonths($monthsBack))
                ->map(fn (Carbon $previous) => ['month' => $previous->toDateString(), 'cost' => round(SeoApiBudget::monthSpend($previous), 2)])
                ->all(),
            'provider_balance' => $isCurrent ? self::dataForSeoBalance() : null,
            'latest_errors' => SeoApiRequest::query()
                ->where('is_success', false)
                ->latest('id')
                ->limit(self::LATEST_ERRORS)
                ->with('website:id,domain')
                ->get()
                ->map(fn (SeoApiRequest $request) => [
                    'id'         => $request->id,
                    'created_at' => $request->created_at->toIso8601String(),
                    'provider'   => $request->provider,
                    'feature'    => self::feature($request->endpoint),
                    'endpoint'   => $request->endpoint,
                    'website'    => $request->website?->domain,
                    'error'      => $request->error,
                ])
                ->all(),
        ];
    }

    /**
     * The money left on the DataForSEO account, which is spent separately from our budget: when it
     * runs out every paid call fails, whatever the budget says.
     */
    public static function dataForSeoBalance(): ?float
    {
        $client = DataForSeoClient::make();

        if (!$client) {
            return null;
        }

        return Cache::remember('dataforseo:balance', now()->addMinutes(self::BALANCE_CACHE_MINUTES), function () use ($client) {
            try {
                return $client->balance();
            } catch (DataForSeoException) {
                return null;
            }
        });
    }

    public static function feature(string $endpoint): string
    {
        foreach (self::FEATURES as $prefix => $feature) {
            if (Str::startsWith($endpoint, $prefix)) {
                return $feature;
            }
        }

        return 'Other';
    }
}
