<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Tue, 06 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Webpage\UI;

use App\Actions\OrgAction;
use App\Actions\Web\WebsiteConversionEvent\GetVisitorLandingWebpage;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\InertiaTable\InertiaTable;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;

class IndexWebpagesPerformance extends OrgAction
{
    public function handle(Website $website, ?string $fromDate = null, ?string $toDate = null, ?string $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $value = strip_tags($value);
                $query->whereAnyWordStartWith('webpages.code', $value)
                    ->orWhereAnyWordStartWith('webpages.url', $value)
                    ->orWhereAnyWordStartWith('webpages.title', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $performance = DB::table('webpage_time_series')
            ->join('webpage_time_series_records', 'webpage_time_series_records.webpage_time_series_id', '=', 'webpage_time_series.id')
            ->where('webpage_time_series.frequency', TimeSeriesFrequencyEnum::DAILY->value)
            ->where('webpage_time_series_records.frequency', TimeSeriesFrequencyEnum::DAILY->singleLetter())
            ->whereIn('webpage_time_series.webpage_id', DB::table('webpages')->where('website_id', $website->id)->select('id'))
            ->when($fromDate, fn ($query) => $query->where('webpage_time_series_records.period', '>=', Carbon::parse($fromDate)->toDateString()))
            ->when($toDate, fn ($query) => $query->where('webpage_time_series_records.period', '<=', Carbon::parse($toDate)->toDateString()))
            ->groupBy('webpage_time_series.webpage_id')
            ->select('webpage_time_series.webpage_id')
            ->selectRaw('SUM(webpage_time_series_records.visitors) as visitors')
            ->selectRaw('SUM(webpage_time_series_records.page_views) as page_views')
            ->selectRaw('SUM(webpage_time_series_records.entrances) as entrances')
            ->selectRaw('SUM(webpage_time_series_records.add_to_baskets) as add_to_baskets')
            ->selectRaw('SUM(webpage_time_series_records.checkouts) as checkouts')
            ->selectRaw('SUM(webpage_time_series_records.purchases) as purchases')
            ->selectRaw('SUM(webpage_time_series_records.avg_time_on_page * webpage_time_series_records.page_views) as total_time_on_page');

        $search = DB::table('search_console_page_days')
            ->where('search_console_page_days.website_id', $website->id)
            ->whereNotNull('search_console_page_days.webpage_id')
            ->when($fromDate, fn ($query) => $query->where('search_console_page_days.date', '>=', Carbon::parse($fromDate)->toDateString()))
            ->when($toDate, fn ($query) => $query->where('search_console_page_days.date', '<=', Carbon::parse($toDate)->toDateString()))
            ->groupBy('search_console_page_days.webpage_id')
            ->select('search_console_page_days.webpage_id')
            ->selectRaw('SUM(search_console_page_days.clicks) as search_clicks')
            ->selectRaw('SUM(search_console_page_days.impressions) as search_impressions')
            ->selectRaw('SUM(search_console_page_days.position * search_console_page_days.impressions) as search_weighted_position');

        $queryBuilder = QueryBuilder::for(Webpage::class)
            ->where('webpages.website_id', $website->id)
            ->leftJoinSub($performance, 'performance', 'performance.webpage_id', '=', 'webpages.id')
            ->leftJoinSub($search, 'search', 'search.webpage_id', '=', 'webpages.id')
            ->where(fn ($query) => $query->whereNotNull('performance.webpage_id')->orWhereNotNull('search.webpage_id'))
            ->leftJoin('organisations', 'webpages.organisation_id', '=', 'organisations.id')
            ->leftJoin('shops', 'webpages.shop_id', '=', 'shops.id')
            ->leftJoin('websites', 'webpages.website_id', '=', 'websites.id');

        return $queryBuilder
            ->defaultSort('-visitors')
            ->select([
                'webpages.id',
                'webpages.slug',
                'webpages.code',
                'webpages.title',
                'webpages.url',
                'webpages.canonical_url',
                'webpages.type',
                'webpages.state',
                'organisations.slug as organisation_slug',
                'shops.slug as shop_slug',
                'websites.slug as website_slug',
            ])
            ->selectRaw('COALESCE(performance.visitors, 0) as visitors')
            ->selectRaw('COALESCE(performance.page_views, 0) as page_views')
            ->selectRaw('COALESCE(performance.entrances, 0) as entrances')
            ->selectRaw('COALESCE(performance.add_to_baskets, 0) as add_to_baskets')
            ->selectRaw('COALESCE(performance.checkouts, 0) as checkouts')
            ->selectRaw('COALESCE(performance.purchases, 0) as purchases')
            ->selectRaw('COALESCE(search.search_clicks, 0) as search_clicks')
            ->selectRaw('COALESCE(search.search_impressions, 0) as search_impressions')
            ->selectRaw('CASE WHEN search.search_impressions > 0 THEN ROUND(search.search_weighted_position / search.search_impressions, 1) END as search_position')
            ->selectRaw('CASE WHEN performance.page_views > 0 THEN ROUND(performance.total_time_on_page / performance.page_views) ELSE 0 END as avg_time_on_page')
            ->selectRaw('CASE WHEN performance.entrances > 0 THEN ROUND(performance.purchases * 100.0 / performance.entrances, 2) ELSE 0 END as conversion_rate')
            ->allowedSorts(['code', 'title', 'visitors', 'page_views', 'avg_time_on_page', 'conversion_rate', 'search_clicks', 'search_impressions', 'search_position'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
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
                ->withLabelRecord([__('webpage'), __('webpages')])
                ->withEmptyState([
                    'title'       => __('No webpage had visitors or Google Search impressions in this period'),
                    'description' => __('Pick a longer interval above to see older days.'),
                ])
                ->column(key: 'type', label: '', icon: 'fal fa-shapes', tooltip: __('Type'), canBeHidden: false, type: 'icon')
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'title', label: __('Title'), sortable: true, searchable: true)
                ->column(key: 'visitors', label: __('Visitors'), tooltip: __('Unique visitors per day, added up, from Aiku tracking'), canBeHidden: false, sortable: true, align: 'right', tooltipIcon: true)
                ->column(key: 'page_views', label: __('Page views'), tooltip: __('Times the page was opened, from Aiku tracking'), sortable: true, align: 'right', tooltipIcon: true)
                ->column(key: 'avg_time_on_page', label: __('Avg. time on page'), tooltip: __('Measured until the visitor opens the next page, so the last page of a visit counts as 0 seconds'), sortable: true, align: 'right', tooltipIcon: true)
                ->column(key: 'conversion_rate', label: __('Conversion'), tooltip: __('Purchases per 100 entrances: visitors whose visit started on this page, once per visitor per day. A visit that continues one from the last 30 minutes, such as after signing in, is not a new entrance. A purchase counts for the page the buyer last landed on in the :days days before', ['days' => GetVisitorLandingWebpage::LOOKBACK_DAYS]), sortable: true, align: 'right', tooltipIcon: true)
                ->column(key: 'search_clicks', label: __('Search clicks'), tooltip: __('Clicks from Google Search, from Search Console'), sortable: true, align: 'right', tooltipIcon: true)
                ->column(key: 'search_impressions', label: __('Impressions'), tooltip: __('Times the page was shown in Google Search, from Search Console'), sortable: true, align: 'right', tooltipIcon: true)
                ->column(key: 'search_position', label: __('Position'), tooltip: __('Average position in Google Search, weighted by impressions. 1 is the top result'), sortable: true, align: 'right', tooltipIcon: true)
                ->defaultSort('-visitors');
        };
    }
}
