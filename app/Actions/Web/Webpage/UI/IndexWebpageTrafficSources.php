<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\Webpage\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\CRM\TrafficSourceClick;
use App\Models\Web\Webpage;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class IndexWebpageTrafficSources extends OrgAction
{
    public const int WINDOW_DAYS = 90;

    public function handle(Webpage $webpage, ?string $prefix = null): LengthAwarePaginator
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(TrafficSourceClick::class)
            ->where('traffic_source_clicks.webpage_id', $webpage->id)
            ->where('traffic_source_clicks.created_at', '>=', now()->subDays(self::WINDOW_DAYS))
            ->where('traffic_source_clicks.is_bot', false)
            ->groupBy('traffic_source_clicks.type')
            ->select('traffic_source_clicks.type')
            ->selectRaw('COUNT(*) as arrivals')
            ->selectRaw('ROUND(COUNT(*) * 100.0 / SUM(COUNT(*)) OVER (), 1) as share')
            ->selectRaw('SUM(CASE WHEN traffic_source_clicks.is_repeat THEN 0 ELSE 1 END) as first_arrivals')
            ->selectRaw('SUM(CASE WHEN traffic_source_clicks.is_repeat THEN 1 ELSE 0 END) as returning_arrivals')
            ->selectRaw('COUNT(DISTINCT traffic_source_clicks.customer_id) as customers')
            ->selectRaw('MAX(traffic_source_clicks.created_at) as last_arrival_at')
            ->defaultSort('-arrivals')
            ->allowedSorts(['type', 'arrivals', 'first_arrivals', 'returning_arrivals', 'customers', 'last_arrival_at'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function totalArrivals(Webpage $webpage): int
    {
        return DB::table('traffic_source_clicks')
            ->where('webpage_id', $webpage->id)
            ->where('created_at', '>=', now()->subDays(self::WINDOW_DAYS))
            ->where('is_bot', false)
            ->count();
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
                ->withLabelRecord([__('source'), __('sources')])
                ->withEmptyState([
                    'title'       => __('No traffic source recorded in the last :days days', ['days' => self::WINDOW_DAYS]),
                    'description' => __('Visitors who come from search, ads, email or other websites appear here.'),
                ])
                ->column(key: 'type', label: __('Source'), canBeHidden: false, sortable: true)
                ->column(key: 'group', label: __('Channel'))
                ->column(key: 'arrivals', label: __('Arrivals'), canBeHidden: false, sortable: true, align: 'right')
                ->column(key: 'share', label: __('Share of total'), align: 'right')
                ->column(key: 'first_arrivals', label: __('First visit'), sortable: true, align: 'right')
                ->column(key: 'returning_arrivals', label: __('Returning'), sortable: true, align: 'right')
                ->column(key: 'customers', label: __('Customers'), sortable: true, align: 'right')
                ->column(key: 'last_arrival_at', label: __('Last arrival'), sortable: true)
                ->defaultSort('-arrivals');
        };
    }
}
