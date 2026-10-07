<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\SearchConsole\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\Web\SearchConsolePageQuery;
use App\Models\Web\Website;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Spatie\QueryBuilder\AllowedFilter;

class IndexSearchConsoleQueries extends OrgAction
{
    public const int OPPORTUNITY_MIN_IMPRESSIONS = 100;

    public const int OPPORTUNITY_MAX_POSITION = 10;

    public const int OPPORTUNITY_MAX_CTR = 2;

    public function handle(Website $website, ?string $fromDate = null, ?string $toDate = null, ?string $prefix = null, bool $lowCtrOnly = false): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where('search_console_page_queries.query', 'ilike', '%'.addcslashes(strip_tags($value), '%_\\').'%');
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(SearchConsolePageQuery::class)
            ->where('search_console_page_queries.website_id', $website->id)
            ->when($fromDate, fn ($query) => $query->where('search_console_page_queries.date', '>=', Carbon::parse($fromDate)->toDateString()))
            ->when($toDate, fn ($query) => $query->where('search_console_page_queries.date', '<=', Carbon::parse($toDate)->toDateString()))
            ->groupBy('search_console_page_queries.query')
            ->select('search_console_page_queries.query')
            ->selectRaw('SUM(search_console_page_queries.clicks) as clicks')
            ->selectRaw('SUM(search_console_page_queries.impressions) as impressions')
            ->selectRaw('ROUND(SUM(search_console_page_queries.clicks) * 100.0 / NULLIF(SUM(search_console_page_queries.impressions), 0), 2) as ctr')
            ->selectRaw('ROUND(SUM(search_console_page_queries.position * search_console_page_queries.impressions) / NULLIF(SUM(search_console_page_queries.impressions), 0), 1) as position')
            ->selectRaw('COUNT(DISTINCT search_console_page_queries.page_url) as pages')
            ->when($lowCtrOnly, function ($query) {
                $query->havingRaw('SUM(search_console_page_queries.impressions) >= ?', [self::OPPORTUNITY_MIN_IMPRESSIONS])
                    ->havingRaw('SUM(search_console_page_queries.position * search_console_page_queries.impressions) / NULLIF(SUM(search_console_page_queries.impressions), 0) <= ?', [self::OPPORTUNITY_MAX_POSITION])
                    ->havingRaw('SUM(search_console_page_queries.clicks) * 100.0 / NULLIF(SUM(search_console_page_queries.impressions), 0) < ?', [self::OPPORTUNITY_MAX_CTR]);
            });

        return $queryBuilder
            ->defaultSort($lowCtrOnly ? '-impressions' : '-clicks')
            ->allowedSorts(['query', 'clicks', 'impressions', 'ctr', 'position', 'pages'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(?string $prefix = null, bool $lowCtrOnly = false): Closure
    {
        return function (InertiaTable $table) use ($prefix, $lowCtrOnly) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $emptyState = $lowCtrOnly
                ? [
                    'title'       => __('No low CTR queries in this period'),
                    'description' => __('A query is listed when it has at least :impressions impressions, an average position of :position or better, and a CTR under :ctr%.', [
                        'impressions' => self::OPPORTUNITY_MIN_IMPRESSIONS,
                        'position'    => self::OPPORTUNITY_MAX_POSITION,
                        'ctr'         => self::OPPORTUNITY_MAX_CTR,
                    ]),
                ]
                : [
                    'title'       => __('No search queries in this period'),
                    'description' => __('Google Search Console data arrives two to three days late. Pick a longer interval above to see older days.'),
                ];

            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('query'), __('queries')])
                ->withEmptyState($emptyState)
                ->column(key: 'query', label: __('Query'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'clicks', label: __('Clicks'), tooltip: __('Clicks from Google Search to this website'), canBeHidden: false, sortable: true, align: 'right', tooltipIcon: true)
                ->column(key: 'impressions', label: __('Impressions'), tooltip: __('Times this website was shown in Google results for the query'), sortable: true, align: 'right', tooltipIcon: true)
                ->column(key: 'ctr', label: __('CTR'), tooltip: __('Clicks per 100 impressions'), sortable: true, align: 'right', tooltipIcon: true)
                ->column(key: 'position', label: __('Position'), tooltip: __('Average position in Google results, weighted by impressions. 1 is the top result'), sortable: true, align: 'right', tooltipIcon: true)
                ->column(key: 'pages', label: __('Pages'), tooltip: __('Pages of this website that Google showed for the query'), sortable: true, align: 'right', tooltipIcon: true)
                ->defaultSort($lowCtrOnly ? '-impressions' : '-clicks');
        };
    }
}
