<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 02 Nov 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\UI;

use App\Actions\OrgAction;
use App\InertiaTable\InertiaTable;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\User;
use App\Models\Web\WebsiteEdgeDatacenterDay;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * The chosen websites' storefront page requests over the dashboard's window (or one day of it),
 * one row per Cloudflare datacenter. Every datacenter keeps its own edge copy, so a page has to
 * be asked for twice at the same one before Cloudflare can answer it without us.
 */
class IndexWebsitesEdgeDatacenters extends OrgAction
{
    private const string REACHED = '(SUM(days.requests) - SUM(days.requests_hit) - SUM(days.requests_none))';

    public function handle(Group $group, User $user, ?string $selected, ?int $days, ?string $day, $prefix = null): LengthAwarePaginator
    {
        [, $chosen] = GetWebsitesEdgeTraffic::make()->chosenWebsites($group, $user, $selected);
        [$from, $to] = GetWebsitesEdgeTraffic::make()->window($days, $day);

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->whereStartWith('days.colo', strtoupper($value));
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $inWindow = fn ($query) => $query
            ->whereIn('days.website_id', $chosen->pluck('id'))
            ->whereBetween('days.date', [$from->toDateString(), $to->toDateString()]);

        $totalRequests = (int) $inWindow(WebsiteEdgeDatacenterDay::from('website_edge_datacenter_days as days'))->sum('days.requests');

        return QueryBuilder::for($inWindow(WebsiteEdgeDatacenterDay::from('website_edge_datacenter_days as days')))
            ->groupBy('days.colo')
            ->defaultSort('-requests')
            ->select([
                'days.colo',
                DB::raw('COUNT(DISTINCT days.website_id) as websites'),
                DB::raw('SUM(days.requests)::bigint as requests'),
                DB::raw('ROUND(SUM(days.requests) * 100.0 / NULLIF('.$totalRequests.', 0), 1) as requests_share'),
                DB::raw('SUM(days.requests_hit)::bigint as requests_hit'),
                DB::raw('ROUND(SUM(days.requests_hit) * 100.0 / NULLIF(SUM(days.requests), 0), 1) as hit_share'),
                DB::raw(self::REACHED.'::bigint as requests_reached'),
                DB::raw('ROUND('.self::REACHED.' * 100.0 / NULLIF(SUM(days.requests), 0), 1) as reached_share'),
                DB::raw('SUM(days.requests_none)::bigint as requests_none'),
            ])
            ->allowedSorts(['colo', 'websites', 'requests', 'requests_share', 'requests_hit', 'hit_share', 'requests_reached', 'reached_share', 'requests_none'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()?->getName())
            ->withQueryString();
    }

    public function tableStructure($prefix = null): Closure
    {
        return function (InertiaTable $table) use ($prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            $count = fn (string $key, string $label, ?string $tooltip = null) => $table->column(
                key: $key,
                label: $label,
                tooltip: $tooltip,
                sortable: true,
                align: 'right',
                tooltipIcon: $tooltip !== null
            );

            $table
                ->withGlobalSearch()
                ->withEmptyState([
                    'title'       => __('No datacenter figures yet'),
                    'description' => __('They are recorded every night for each live storefront'),
                ])
                ->column(key: 'colo', label: __('Datacenter'), canBeHidden: false, sortable: true, searchable: true);

            $count('requests', __('Requests'), __('Storefront page requests this Cloudflare datacenter received'));
            $count('requests_share', __('Requests %'), __('Share of all the requests in this list'));
            $count('requests_hit', __('Edge copy'), __('Answered from this datacenter\'s own copy'));
            $count('hit_share', __('Edge copy %'));
            $count('requests_reached', __('Reached us'), __('Requests this datacenter had to fetch from our servers'));
            $count('reached_share', __('Reached us %'));
            $count('requests_none', __('Rules'), __('Answered by Cloudflare rules: blocks, challenges'));
            $count('websites', __('Websites'));

            $table->defaultSort('-requests');
        };
    }
}
