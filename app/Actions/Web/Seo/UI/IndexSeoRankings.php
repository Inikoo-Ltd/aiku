<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Seo\UI;

use App\Actions\OrgAction;
use App\Enums\Web\Seo\SeoKeywordDeviceEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Shop;
use App\Models\Web\SeoTrackedKeyword;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

class IndexSeoRankings extends OrgAction
{
    use WithSeoKeywordFilters;

    public const int SEARCH_CONSOLE_DAYS = 28;

    /**
     * One row per keyword on desktop, the only device tracked. The row shows the country where it ranks best,
     * and `other_countries` holds the rest, so a keyword tracked in many countries is one line that
     * opens into the others.
     */
    public function handle(Shop $shop, ?string $prefix = null): LengthAwarePaginator
    {
        $device = SeoKeywordDeviceEnum::DESKTOP;

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where('seo_tracked_keywords.keyword', 'ilike', '%'.addcslashes(strip_tags($value), '%_\\').'%');
        });

        $keywords = QueryBuilder::for(SeoTrackedKeyword::class)
            ->where('seo_tracked_keywords.shop_id', $shop->id)
            ->where('seo_tracked_keywords.is_active', true)
            ->where('seo_tracked_keywords.device', $device->value)
            ->leftJoin('seo_keywords', function ($join) {
                $join->on('seo_keywords.shop_id', '=', 'seo_tracked_keywords.shop_id')
                    ->on('seo_keywords.keyword', '=', 'seo_tracked_keywords.keyword')
                    ->on('seo_keywords.country_code', '=', 'seo_tracked_keywords.country_code')
                    ->on('seo_keywords.language_code', '=', 'seo_tracked_keywords.language_code');
            })
            ->where(fn ($query) => $this->applyKeywordFilters($query, $this->keywordFilters($prefix)))
            ->groupBy('seo_tracked_keywords.keyword')
            ->defaultSort('keyword')
            ->select(['seo_tracked_keywords.keyword'])
            ->allowedSorts([
                AllowedSort::field('keyword', 'seo_tracked_keywords.keyword'),
                AllowedSort::callback('position', fn ($query, bool $descending) => $query->orderByRaw('MIN(seo_tracked_keywords.position) '.($descending ? 'DESC' : 'ASC').' NULLS LAST')),
                AllowedSort::callback('avg_monthly_searches', fn ($query, bool $descending) => $query->orderByRaw('MAX(seo_keywords.avg_monthly_searches) '.($descending ? 'DESC' : 'ASC').' NULLS LAST')),
                AllowedSort::callback('keyword_difficulty', fn ($query, bool $descending) => $query->orderByRaw('MAX(seo_keywords.keyword_difficulty) '.($descending ? 'DESC' : 'ASC').' NULLS LAST')),
            ])
            ->allowedFilters([
                $globalSearch,
                AllowedFilter::exact('intent', 'seo_keywords.intent'),
            ])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();

        $variants = $this->variantRows($shop, $device, $keywords->getCollection()->pluck('keyword')->all());

        $this->attach($shop, $variants);

        $variantsByKeyword = $variants->groupBy('keyword');

        $this->attachTags($shop->id, $keywords->getCollection());

        return $keywords->through(function (SeoTrackedKeyword $keyword) use ($variantsByKeyword) {
            $variants = $variantsByKeyword
                ->get($keyword->keyword, collect())
                ->sortBy([
                    fn (SeoTrackedKeyword $a, SeoTrackedKeyword $b) => ($a->position ?? PHP_INT_MAX) <=> ($b->position ?? PHP_INT_MAX),
                    fn (SeoTrackedKeyword $a, SeoTrackedKeyword $b) => ($b->avg_monthly_searches ?? -1) <=> ($a->avg_monthly_searches ?? -1),
                    fn (SeoTrackedKeyword $a, SeoTrackedKeyword $b) => $a->country_code <=> $b->country_code,
                ])
                ->values();

            /** @var SeoTrackedKeyword $primary */
            $primary = $variants->first();

            $primary->setAttribute('other_countries', $variants->slice(1)->values());
            $primary->setAttribute('check_ids', $variants->pluck('id')->all());
            $primary->setAttribute('tags', $keyword->tags);

            return $primary;
        });
    }

    /**
     * Every country of the given keywords on one device.
     *
     * @param  array<int, string>  $keywords
     * @return Collection<int, SeoTrackedKeyword>
     */
    private function variantRows(Shop $shop, SeoKeywordDeviceEnum $device, array $keywords): Collection
    {
        return SeoTrackedKeyword::query()
            ->where('seo_tracked_keywords.shop_id', $shop->id)
            ->where('seo_tracked_keywords.is_active', true)
            ->where('seo_tracked_keywords.device', $device->value)
            ->whereIn('seo_tracked_keywords.keyword', $keywords)
            ->leftJoin('seo_keywords', function ($join) {
                $join->on('seo_keywords.shop_id', '=', 'seo_tracked_keywords.shop_id')
                    ->on('seo_keywords.keyword', '=', 'seo_tracked_keywords.keyword')
                    ->on('seo_keywords.country_code', '=', 'seo_tracked_keywords.country_code')
                    ->on('seo_keywords.language_code', '=', 'seo_tracked_keywords.language_code');
            })
            ->select($this->columns())
            ->get();
    }

    /**
     * @return array<int, string>
     */
    private function columns(): array
    {
        return [
            'seo_tracked_keywords.id',
            'seo_tracked_keywords.keyword',
            'seo_tracked_keywords.country_code',
            'seo_tracked_keywords.language_code',
            'seo_tracked_keywords.device',
            'seo_tracked_keywords.frequency',
            'seo_tracked_keywords.last_checked_at',
            'seo_tracked_keywords.position',
            'seo_tracked_keywords.previous_position',
            'seo_tracked_keywords.previous_checked_at',
            'seo_tracked_keywords.ranking_url',
            'seo_tracked_keywords.serp_features',
            'seo_tracked_keywords.in_ai_overview',
            'seo_tracked_keywords.serp_features_owned',
            'seo_tracked_keywords.pending_task_id',
            'seo_keywords.avg_monthly_searches',
            'seo_keywords.intent',
            'seo_keywords.keyword_difficulty',
        ];
    }

    /**
     * One row per keyword and country on desktop, for the Excel export.
     */
    public function variants(Shop $shop, ?string $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where('seo_tracked_keywords.keyword', 'ilike', '%'.addcslashes(strip_tags($value), '%_\\').'%');
        });

        $rankings = QueryBuilder::for(SeoTrackedKeyword::class)
            ->where('seo_tracked_keywords.shop_id', $shop->id)
            ->where('seo_tracked_keywords.is_active', true)
            ->where('seo_tracked_keywords.device', SeoKeywordDeviceEnum::DESKTOP->value)
            ->leftJoin('seo_keywords', function ($join) {
                $join->on('seo_keywords.shop_id', '=', 'seo_tracked_keywords.shop_id')
                    ->on('seo_keywords.keyword', '=', 'seo_tracked_keywords.keyword')
                    ->on('seo_keywords.country_code', '=', 'seo_tracked_keywords.country_code')
                    ->on('seo_keywords.language_code', '=', 'seo_tracked_keywords.language_code');
            })
            ->where(fn ($query) => $this->applyKeywordFilters($query, $this->keywordFilters($prefix)))
            ->defaultSort('seo_tracked_keywords.keyword')
            ->select($this->columns())
            ->allowedSorts([
                AllowedSort::field('keyword', 'seo_tracked_keywords.keyword'),
                AllowedSort::callback('position', fn ($query, bool $descending) => $query->orderByRaw('seo_tracked_keywords.position '.($descending ? 'DESC' : 'ASC').' NULLS LAST')),
                AllowedSort::callback('avg_monthly_searches', fn ($query, bool $descending) => $query->orderByRaw('seo_keywords.avg_monthly_searches '.($descending ? 'DESC' : 'ASC').' NULLS LAST')),
                AllowedSort::callback('keyword_difficulty', fn ($query, bool $descending) => $query->orderByRaw('seo_keywords.keyword_difficulty '.($descending ? 'DESC' : 'ASC').' NULLS LAST')),
            ])
            ->allowedFilters([
                $globalSearch,
                AllowedFilter::exact('intent', 'seo_keywords.intent'),
            ])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();

        $this->attach($shop, $rankings->getCollection());
        $this->attachTags($shop->id, $rankings->getCollection());

        return $rankings;
    }

    /**
     * @param  Collection<int, SeoTrackedKeyword>  $rankings
     */
    private function attach(Shop $shop, Collection $rankings): void
    {
        $this->attachSearchConsolePositions($shop, $rankings);
        $this->attachCompetitorPositions($rankings);
        $this->attachWatchesAndAlerts($rankings);
    }

    private function attachSearchConsolePositions(Shop $shop, Collection $rankings): void
    {
        $keywords = $rankings->pluck('keyword')->unique()->values()->all();

        if (!$shop->website || $keywords === []) {
            return;
        }

        $positions = DB::connection('aiku_no_sticky')->table('search_console_page_queries')
            ->where('website_id', $shop->website->id)
            ->where('date', '>=', now()->subDays(self::SEARCH_CONSOLE_DAYS)->toDateString())
            ->whereIn(DB::raw('lower(query)'), $keywords)
            ->groupBy(DB::raw('lower(query)'))
            ->selectRaw('lower(query) as keyword')
            ->selectRaw('ROUND(SUM(position * impressions) / NULLIF(SUM(impressions), 0), 1) as position')
            ->pluck('position', 'keyword');

        $rankings->each(fn (SeoTrackedKeyword $trackedKeyword) => $trackedKeyword->setAttribute('search_console_position', $positions[$trackedKeyword->keyword] ?? null));
    }

    private function attachWatchesAndAlerts(Collection $rankings): void
    {
        $ids = $rankings->pluck('id')->all();

        if ($ids === []) {
            return;
        }

        $watched = DB::table('seo_keyword_watchers')
            ->whereIn('tracked_keyword_id', $ids)
            ->where('user_id', request()->user()?->id)
            ->pluck('tracked_keyword_id')
            ->flip();

        $alerts = DB::table('seo_ranking_alerts')
            ->join('seo_tracked_keywords', function ($join) {
                $join->on('seo_tracked_keywords.id', '=', 'seo_ranking_alerts.tracked_keyword_id')
                    ->whereRaw('seo_ranking_alerts.date = seo_tracked_keywords.last_checked_at::date');
            })
            ->whereIn('seo_ranking_alerts.tracked_keyword_id', $ids)
            ->pluck('seo_ranking_alerts.reason', 'seo_ranking_alerts.tracked_keyword_id');

        $rankings->each(function (SeoTrackedKeyword $trackedKeyword) use ($watched, $alerts) {
            $trackedKeyword->setAttribute('is_watched', $watched->has($trackedKeyword->id));
            $trackedKeyword->setAttribute('alert', $alerts->get($trackedKeyword->id));
        });
    }

    private function attachCompetitorPositions(Collection $rankings): void
    {
        $checked = $rankings->filter(fn (SeoTrackedKeyword $trackedKeyword) => $trackedKeyword->last_checked_at !== null);

        if ($checked->isEmpty()) {
            return;
        }

        $positions = DB::table('seo_competitor_rankings')
            ->join('seo_tracked_keywords', function ($join) {
                $join->on('seo_tracked_keywords.id', '=', 'seo_competitor_rankings.tracked_keyword_id')
                    ->whereRaw('seo_competitor_rankings.date = seo_tracked_keywords.last_checked_at::date');
            })
            ->join('seo_competitors', 'seo_competitors.id', '=', 'seo_competitor_rankings.competitor_id')
            ->whereIn('seo_competitor_rankings.tracked_keyword_id', $checked->pluck('id'))
            ->orderBy('seo_competitors.domain')
            ->get(['seo_competitor_rankings.tracked_keyword_id', 'seo_competitors.domain', 'seo_competitors.label', 'seo_competitor_rankings.position', 'seo_competitor_rankings.url'])
            ->groupBy('tracked_keyword_id');

        $checked->each(fn (SeoTrackedKeyword $trackedKeyword) => $trackedKeyword->setAttribute(
            'competitor_positions',
            $positions->get($trackedKeyword->id, collect())->map(fn ($row) => [
                'domain'   => $row->domain,
                'label'    => $row->label,
                'position' => $row->position,
                'url'      => $row->url,
            ])->values()->all()
        ));
    }

    public function tableStructure(?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('keyword'), __('keywords')])
                ->withEmptyState([
                    'title'       => __('No keywords tracked yet'),
                    'description' => __('Add keywords in the Tracked keywords tab. Their Google positions appear here after the first check.'),
                ])
                ->column(key: 'keyword', label: __('Keyword'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'position', label: __('Position'), tooltip: __('Our organic position in Google for the country and device, from the latest check. Weekly keywords are read to the top 30, daily ones to the top 20'), sortable: true, align: 'right', tooltipIcon: true)
                ->column(key: 'change', label: __('Change'), tooltip: __('Since the previous check'), align: 'right', tooltipIcon: true)
                ->column(key: 'search_console_position', label: __('Search Console'), tooltip: __('Average position over all impressions in the last 28 days, every country and device. It measures something different from one check, so the two rarely match'), align: 'right', tooltipIcon: true)
                ->column(key: 'ranking_url', label: __('Ranking page'))
                ->column(key: 'avg_monthly_searches', label: __('Monthly searches'), sortable: true, align: 'right')
                ->column(key: 'keyword_difficulty', label: __('KD'), tooltip: __('Keyword difficulty, 0 to 100: how hard it is to reach the top 10, from DataForSEO. A dash means no data, which is not the same as 0'), sortable: true, align: 'right', tooltipIcon: true)
                ->column(key: 'intent', label: __('Intent'))
                ->column(key: 'tags', label: __('Tags'))
                ->column(key: 'serp_features', label: __('On the results page'), tooltip: __('What else Google showed for the keyword. A feature is highlighted when it shows our website'), tooltipIcon: true)
                ->column(key: 'competitor_positions', label: __('Competitors'))
                ->defaultSort('keyword');
        };
    }
}
