<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Tue, 06 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Webpage\UI;

use App\Actions\OrgAction;
use App\Actions\Web\Seo\GetSeoAiVisibility;
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
    /**
     * Below this many visitors or clicks in both periods a change is shown as a difference, not a
     * percentage: going from 2 to 4 is +100% and means nothing.
     */
    public const int MIN_FOR_PERCENT = 20;

    /**
     * The previous period ends the day before the interval starts and has the same number of days.
     * Search Console data stops a few days before today, so its periods end on its own last day.
     *
     * @return array{traffic: array{from: string, to: string, previous_from: string, previous_to: string}|null, search: array{from: string, to: string, previous_from: string, previous_to: string}|null}
     */
    public static function periods(Website $website, ?string $fromDate, ?string $toDate): array
    {
        if (!$fromDate) {
            return ['traffic' => null, 'search' => null];
        }

        $from = Carbon::parse($fromDate)->startOfDay();
        $to   = Carbon::parse($toDate ?? today())->startOfDay();

        $lastSearchDay = DB::table('search_console_page_days')->where('website_id', $website->id)->max('date');
        $searchTo      = $lastSearchDay ? Carbon::parse($lastSearchDay)->min($to) : null;

        $period = fn (Carbon $from, Carbon $to) => $to->lt($from) ? null : [
            'from'          => $from->toDateString(),
            'to'            => $to->toDateString(),
            'previous_from' => $from->copy()->subDays((int) $from->diffInDays($to) + 1)->toDateString(),
            'previous_to'   => $from->copy()->subDay()->toDateString(),
        ];

        return [
            'traffic' => $period($from, $to),
            'search'  => $searchTo ? $period($from, $searchTo) : null,
        ];
    }

    public function handle(Website $website, ?string $fromDate = null, ?string $toDate = null, ?string $prefix = null): LengthAwarePaginator
    {
        $periods = self::periods($website, $fromDate, $toDate);

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

        $previousPerformance = $periods['traffic'] ? DB::table('webpage_time_series')
            ->join('webpage_time_series_records', 'webpage_time_series_records.webpage_time_series_id', '=', 'webpage_time_series.id')
            ->where('webpage_time_series.frequency', TimeSeriesFrequencyEnum::DAILY->value)
            ->where('webpage_time_series_records.frequency', TimeSeriesFrequencyEnum::DAILY->singleLetter())
            ->whereIn('webpage_time_series.webpage_id', DB::table('webpages')->where('website_id', $website->id)->select('id'))
            ->whereBetween('webpage_time_series_records.period', [$periods['traffic']['previous_from'], $periods['traffic']['previous_to']])
            ->groupBy('webpage_time_series.webpage_id')
            ->select('webpage_time_series.webpage_id')
            ->selectRaw('SUM(webpage_time_series_records.visitors) as visitors')
            ->selectRaw('SUM(webpage_time_series_records.page_views) as page_views') : null;

        $previousSearch = $periods['search'] ? DB::table('search_console_page_days')
            ->where('search_console_page_days.website_id', $website->id)
            ->whereNotNull('search_console_page_days.webpage_id')
            ->whereBetween('search_console_page_days.date', [$periods['search']['previous_from'], $periods['search']['previous_to']])
            ->groupBy('search_console_page_days.webpage_id')
            ->select('search_console_page_days.webpage_id')
            ->selectRaw('SUM(search_console_page_days.clicks) as search_clicks')
            ->selectRaw('SUM(search_console_page_days.impressions) as search_impressions')
            ->selectRaw('SUM(search_console_page_days.position * search_console_page_days.impressions) as search_weighted_position') : null;

        $queries = DB::table('search_console_page_queries')
            ->where('website_id', $website->id)
            ->whereNotNull('webpage_id')
            ->when($fromDate, fn ($query) => $query->where('date', '>=', Carbon::parse($fromDate)->toDateString()))
            ->when($toDate, fn ($query) => $query->where('date', '<=', Carbon::parse($toDate)->toDateString()))
            ->groupBy('webpage_id')
            ->select('webpage_id')
            ->selectRaw('COUNT(DISTINCT query) as search_queries');

        $backlinks = DB::table('seo_backlinks')
            ->where('website_id', $website->id)
            ->whereNotNull('target_webpage_id')
            ->whereNull('lost_at')
            ->where('is_own_website', false)
            ->groupBy('target_webpage_id')
            ->select('target_webpage_id')
            ->selectRaw('COUNT(*) as backlinks')
            ->selectRaw('COUNT(DISTINCT source_domain) as referring_domains');

        $aiPrompts = DB::table('seo_ai_citations')
            ->join('seo_ai_answers', 'seo_ai_answers.id', '=', 'seo_ai_citations.answer_id')
            ->where('seo_ai_citations.website_id', $website->id)
            ->whereNotNull('seo_ai_citations.webpage_id')
            ->where('seo_ai_answers.date', '>=', today()->subDays(GetSeoAiVisibility::DAYS - 1)->toDateString())
            ->groupBy('seo_ai_citations.webpage_id')
            ->select('seo_ai_citations.webpage_id')
            ->selectRaw('COUNT(DISTINCT seo_ai_answers.prompt_id) as ai_prompts');

        $queryBuilder = QueryBuilder::for(Webpage::class)
            ->where('webpages.website_id', $website->id)
            ->leftJoinSub($performance, 'performance', 'performance.webpage_id', '=', 'webpages.id')
            ->leftJoinSub($search, 'search', 'search.webpage_id', '=', 'webpages.id')
            ->leftJoinSub($queries, 'queries', 'queries.webpage_id', '=', 'webpages.id')
            ->leftJoinSub($backlinks, 'backlinks', 'backlinks.target_webpage_id', '=', 'webpages.id')
            ->leftJoinSub($aiPrompts, 'ai_prompts', 'ai_prompts.webpage_id', '=', 'webpages.id')
            ->when($previousPerformance, fn ($query) => $query->leftJoinSub($previousPerformance, 'previous_performance', 'previous_performance.webpage_id', '=', 'webpages.id'))
            ->when($previousSearch, fn ($query) => $query->leftJoinSub($previousSearch, 'previous_search', 'previous_search.webpage_id', '=', 'webpages.id'))
            ->where(fn ($query) => $query
                ->whereNotNull('performance.webpage_id')
                ->orWhereNotNull('search.webpage_id')
                ->when($previousPerformance, fn ($query) => $query->orWhereNotNull('previous_performance.webpage_id'))
                ->when($previousSearch, fn ($query) => $query->orWhereNotNull('previous_search.webpage_id')))
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
            ->selectRaw('COALESCE(queries.search_queries, 0) as search_queries')
            ->selectRaw('COALESCE(backlinks.backlinks, 0) as backlinks')
            ->selectRaw('COALESCE(backlinks.referring_domains, 0) as referring_domains')
            ->selectRaw('COALESCE(ai_prompts.ai_prompts, 0) as ai_prompts')
            ->selectRaw($previousPerformance ? 'COALESCE(previous_performance.visitors, 0) as previous_visitors' : 'NULL as previous_visitors')
            ->selectRaw($previousPerformance ? 'COALESCE(previous_performance.page_views, 0) as previous_page_views' : 'NULL as previous_page_views')
            ->selectRaw($previousSearch ? 'COALESCE(previous_search.search_clicks, 0) as previous_search_clicks' : 'NULL as previous_search_clicks')
            ->selectRaw($previousSearch ? 'COALESCE(previous_search.search_impressions, 0) as previous_search_impressions' : 'NULL as previous_search_impressions')
            ->selectRaw($previousSearch ? 'CASE WHEN previous_search.search_impressions > 0 THEN ROUND(previous_search.search_weighted_position / previous_search.search_impressions, 1) END as previous_search_position' : 'NULL as previous_search_position')
            ->selectRaw($previousPerformance ? 'COALESCE(performance.visitors, 0) - COALESCE(previous_performance.visitors, 0) as visitors_change' : 'NULL as visitors_change')
            ->selectRaw($previousSearch ? 'COALESCE(search.search_clicks, 0) - COALESCE(previous_search.search_clicks, 0) as search_clicks_change' : 'NULL as search_clicks_change')
            ->allowedSorts(['code', 'title', 'visitors', 'page_views', 'avg_time_on_page', 'conversion_rate', 'search_clicks', 'search_impressions', 'search_position', 'search_queries', 'referring_domains', 'ai_prompts', 'visitors_change', 'search_clicks_change'])
            ->allowedFilters([
                $globalSearch,
                AllowedFilter::callback('trend', function ($query, $value) use ($previousPerformance) {
                    if (!$previousPerformance) {
                        return;
                    }

                    $current  = 'COALESCE(performance.visitors, 0)';
                    $previous = 'COALESCE(previous_performance.visitors, 0)';

                    match ($value) {
                        'growing'  => $query->whereRaw("$current > $previous AND $previous > 0"),
                        'dropping' => $query->whereRaw("$current < $previous AND $current > 0"),
                        'new'      => $query->whereRaw("$previous = 0 AND $current > 0"),
                        'lost'     => $query->whereRaw("$current = 0 AND $previous > 0"),
                        default    => null,
                    };
                }),
            ])
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
                ->column(key: 'search_queries', label: __('Queries'), tooltip: __('Different Google searches the page appeared for, from Search Console'), sortable: true, align: 'right', tooltipIcon: true)
                ->column(key: 'referring_domains', label: __('Referring domains'), tooltip: __('Other websites linking to the page, from our monthly backlink list, our own websites left out. The list holds one link per linking domain, so it counts domains better than links'), sortable: true, align: 'right', tooltipIcon: true)
                ->column(key: 'ai_prompts', label: __('AI prompts'), tooltip: __('Our AI visibility prompts whose ChatGPT answer cited the page in the last :days days, whatever the interval', ['days' => GetSeoAiVisibility::DAYS]), sortable: true, align: 'right', tooltipIcon: true)
                ->defaultSort('-visitors');
        };
    }
}
