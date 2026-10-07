<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Tue, 06 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Webpage\UI;

use App\Actions\OrgAction;
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
            ->selectRaw('SUM(webpage_time_series_records.add_to_baskets) as add_to_baskets')
            ->selectRaw('SUM(webpage_time_series_records.avg_time_on_page * webpage_time_series_records.page_views) as total_time_on_page');

        $queryBuilder = QueryBuilder::for(Webpage::class)
            ->where('webpages.website_id', $website->id)
            ->joinSub($performance, 'performance', 'performance.webpage_id', '=', 'webpages.id')
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
                'performance.visitors',
                'performance.page_views',
                'performance.add_to_baskets',
            ])
            ->selectRaw('CASE WHEN performance.page_views > 0 THEN ROUND(performance.total_time_on_page / performance.page_views) ELSE 0 END as avg_time_on_page')
            ->selectRaw('CASE WHEN performance.visitors > 0 THEN ROUND(performance.add_to_baskets * 100.0 / performance.visitors, 2) ELSE 0 END as conversion_rate')
            ->allowedSorts(['code', 'title', 'visitors', 'page_views', 'avg_time_on_page', 'add_to_baskets', 'conversion_rate'])
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
                    'title'       => __('No webpage had visitors in this period'),
                    'description' => __('Pick a longer interval above to see older days.'),
                ])
                ->column(key: 'type', label: '', icon: 'fal fa-shapes', tooltip: __('Type'), canBeHidden: false, type: 'icon')
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'title', label: __('Title'), sortable: true, searchable: true)
                ->column(key: 'visitors', label: __('Visitors'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'page_views', label: __('Page views'), sortable: true, align: 'right')
                ->column(key: 'avg_time_on_page', label: __('Avg. time on page'), sortable: true, align: 'right')
                ->column(key: 'add_to_baskets', label: __('Add to baskets'), sortable: true, align: 'right')
                ->column(key: 'conversion_rate', label: __('Conversion'), tooltip: __('Add to baskets per 100 visitors'), sortable: true, align: 'right')
                ->defaultSort('-visitors');
        };
    }
}
