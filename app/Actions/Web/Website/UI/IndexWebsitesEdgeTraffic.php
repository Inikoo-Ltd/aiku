<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 01 Nov 2026 22:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\UI;

use App\Actions\OrgAction;
use App\Actions\Web\Website\Cloudflare\FetchWebsiteEdgeTimeSeries;
use App\InertiaTable\InertiaTable;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\User;
use App\Models\Web\Website;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;

/**
 * What Cloudflare did with each website's storefront pages over the dashboard's window (or one
 * day of it), one row per website, so any figure of the overview can be traced to the sites
 * behind it.
 */
class IndexWebsitesEdgeTraffic extends OrgAction
{
    private const string EDGE  = '(SUM(records.requests_hit) + SUM(records.requests_none))';

    private const string REACHED = '(SUM(records.requests) - SUM(records.requests_hit) - SUM(records.requests_none))';

    public function handle(Group $group, User $user, ?string $selected, ?int $days, ?string $day, $prefix = null): LengthAwarePaginator
    {
        [, $chosen] = GetWebsitesEdgeTraffic::make()->chosenWebsites($group, $user, $selected);
        [$from, $to] = GetWebsitesEdgeTraffic::make()->window($days, $day);

        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('websites.domain', $value)
                    ->orWhereStartWith('organisations.name', $value);
            });
        });

        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        return QueryBuilder::for(Website::class)
            ->join('organisations', 'organisations.id', 'websites.organisation_id')
            ->join('website_edge_time_series as series', fn ($join) => $join->on('series.website_id', 'websites.id')->where('series.frequency', 'daily'))
            ->join('website_edge_time_series_records as records', 'records.website_edge_time_series_id', 'series.id')
            ->whereIn('websites.id', $chosen->pluck('id'))
            ->whereBetween('records.from', [$from, $to])
            ->groupBy('websites.id', 'organisations.id')
            ->defaultSort('-requests')
            ->select([
                'websites.id',
                'websites.slug',
                'websites.domain',
                'organisations.name as organisation_name',
                ...array_map(fn (string $metric) => DB::raw("SUM(records.$metric)::bigint as $metric"), FetchWebsiteEdgeTimeSeries::METRICS),
                DB::raw(self::REACHED.'::bigint as requests_reached'),
                DB::raw('SUM(records.requests_octane)::bigint as requests_octane'),
                DB::raw('SUM(CASE WHEN records.requests_octane IS NOT NULL THEN GREATEST(0, records.requests - records.requests_hit - records.requests_none - records.requests_octane) END)::bigint as requests_varnish'),
                DB::raw("ROUND(".self::EDGE." * 100.0 / NULLIF(SUM(records.requests), 0), 1) as edge_share"),
                DB::raw('ROUND(SUM(records.requests_4xx) * 100.0 / NULLIF(SUM(records.requests), 0), 1) as share_4xx'),
                DB::raw('ROUND(SUM(records.requests_unknown_agent) * 100.0 / NULLIF(SUM(records.requests), 0), 1) as unknown_agent_share'),
                DB::raw('ROUND(SUM(records.requests_far_countries) * 100.0 / NULLIF(SUM(records.requests), 0), 1) as far_share'),
                DB::raw('ROUND(SUM(records.origin_response_ms_total) / NULLIF('.self::REACHED.', 0)) as avg_origin_ms'),
            ])
            ->allowedSorts([
                'domain',
                ...FetchWebsiteEdgeTimeSeries::METRICS,
                'requests_reached',
                'requests_varnish',
                'requests_octane',
                'edge_share',
                'share_4xx',
                'unknown_agent_share',
                'far_share',
                'avg_origin_ms',
            ])
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

            $count = fn (string $key, string $label, ?string $tooltip = null, bool $hidden = false) => $table->column(
                key: $key,
                label: $label,
                tooltip: $tooltip,
                hidden: $hidden,
                sortable: true,
                align: 'right',
                tooltipIcon: $tooltip !== null
            );

            $table
                ->withGlobalSearch()
                ->withEmptyState([
                    'title'       => __('No Cloudflare figures yet'),
                    'description' => __('They are recorded every night for each live storefront'),
                ])
                ->column(key: 'domain', label: __('Website'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'organisation_name', label: __('Organisation'));

            $count('requests', __('Requests'), __('Storefront page requests Cloudflare received'));
            $count('edge_share', __('Edge %'), __('Share Cloudflare answered itself, from its copy or its own rules'));
            $count('requests_reached', __('Reached us'), __('Requests Cloudflare had to fetch from our servers'));
            $count('requests_varnish', __('Varnish'), __('Of what reached us, answered from the Varnish copy. Only days when Octane pages were counted, so with Octane it can be less than Reached us'));
            $count('requests_octane', __('Octane'), __('Of what reached us, pages Octane rendered. Only days when they were counted'));
            $count('avg_origin_ms', __('Origin ms'), __('Average time our servers took to answer, as Cloudflare measured it'));
            $count('requests_hit', __('Hit'), hidden: true);
            $count('requests_miss', __('Miss'), hidden: true);
            $count('requests_expired', __('Expired'), hidden: true);
            $count('requests_dynamic', __('Dynamic'), hidden: true);
            $count('requests_bypass', __('Bypass'), hidden: true);
            $count('requests_none', __('Rules'), __('Answered by Cloudflare rules: redirects, blocks'), hidden: true);
            $count('requests_2xx', __('2xx'), hidden: true);
            $count('requests_3xx', __('3xx'), hidden: true);
            $count('requests_4xx', __('4xx'));
            $count('share_4xx', __('4xx %'));
            $count('requests_5xx', __('5xx'));
            $count('requests_google_bot', __('Google'), hidden: true);
            $count('requests_bing_bot', __('Bing'), hidden: true);
            $count('requests_apple_bot', __('Apple'), hidden: true);
            $count('requests_other_bots', __('Other bots'), hidden: true);
            $count('requests_unknown_agent', __('Unknown agent'), __('Traffic Cloudflare could not classify, mostly scrapers'));
            $count('unknown_agent_share', __('Unknown %'));
            $count('requests_browsers', __('Browsers'));
            $count('far_share', __('Far %'), __('Share from the far scraper countries'));
            $count('requests_far_countries_hit', __('Far edge'), __('Far country requests answered from the Cloudflare copy'), hidden: true);

            $table->defaultSort('-requests');
        };
    }
}
