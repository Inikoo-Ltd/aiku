<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 07 Oct 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Web\WebVital\UI;

use App\Actions\OrgAction;
use App\Actions\Web\WebVital\GetWebsitePageSpeedSummary;
use App\Actions\Web\WebVital\GetWebVitalsReport;
use App\InertiaTable\InertiaTable;
use App\Models\Web\Webpage;
use App\Models\Web\Website;
use App\Services\QueryBuilder;
use Closure;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;

class IndexWebpagesPageSpeed extends OrgAction
{
    public const array LCP_LIMITS = [2500, 4000];

    public const array INP_LIMITS = [200, 500];

    public const array CLS_LIMITS = [0.1, 0.25];

    private function elementGroups(Website $website): array
    {
        $pagesPerDevice = DB::connection('aiku_no_sticky')->query()
            ->fromSub(
                DB::connection('aiku_no_sticky')->table('web_vital_samples')
                    ->where('website_id', $website->id)
                    ->whereNotNull('webpage_id')
                    ->where('created_at', '>=', now()->subDays(GetWebsitePageSpeedSummary::VISITOR_DAYS)->startOfDay())
                    ->groupBy('webpage_id', 'device')
                    ->havingRaw('COUNT(*) >= ?', [GetWebVitalsReport::MIN_SAMPLES])
                    ->select('webpage_id', 'device'),
                'measured_pages'
            )
            ->groupBy('device')
            ->selectRaw('device, COUNT(*) as pages')
            ->pluck('pages', 'device');

        return [
            'device' => [
                'label'    => __('Device'),
                'elements' => [
                    'phone'   => [__('Mobile'), (int) $pagesPerDevice->get('phone', 0)],
                    'desktop' => [__('Desktop'), (int) $pagesPerDevice->get('desktop', 0)],
                ],
            ],
        ];
    }

    public function handle(Website $website, ?string $prefix = null): LengthAwarePaginator
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

        $devices = ['phone', 'desktop'];

        $queryBuilder = QueryBuilder::for(Webpage::class);

        foreach ($this->elementGroups($website) as $key => $elementGroup) {
            $queryBuilder->whereElementGroup(
                key: $key,
                allowedElements: array_keys($elementGroup['elements']),
                engine: function ($query, array $elements) use (&$devices) {
                    $devices = array_values($elements);
                },
                prefix: $prefix,
                default: 'phone'
            );
        }

        $samples = DB::table('web_vital_samples')
            ->where('website_id', $website->id)
            ->whereNotNull('webpage_id')
            ->where('created_at', '>=', now()->subDays(GetWebsitePageSpeedSummary::VISITOR_DAYS)->startOfDay())
            ->whereIn('device', $devices)
            ->groupBy('webpage_id')
            ->havingRaw('COUNT(*) >= ?', [GetWebVitalsReport::MIN_SAMPLES])
            ->select('webpage_id')
            ->selectRaw('COUNT(*) as samples')
            ->selectRaw('percentile_cont(0.75) within group (order by lcp) as lcp')
            ->selectRaw('percentile_cont(0.75) within group (order by inp) as inp')
            ->selectRaw('percentile_cont(0.75) within group (order by cls) as cls');

        $rating = fn (string $metric, array $limits) => "CASE WHEN speed.$metric IS NULL THEN NULL WHEN speed.$metric <= $limits[0] THEN 0 WHEN speed.$metric <= $limits[1] THEN 1 ELSE 2 END";

        $statusSql = 'GREATEST('.$rating('lcp', self::LCP_LIMITS).', '.$rating('inp', self::INP_LIMITS).', '.$rating('cls', self::CLS_LIMITS).')';

        return $queryBuilder
            ->where('webpages.website_id', $website->id)
            ->joinSub($samples, 'speed', 'speed.webpage_id', '=', 'webpages.id')
            ->leftJoin('organisations', 'webpages.organisation_id', '=', 'organisations.id')
            ->leftJoin('shops', 'webpages.shop_id', '=', 'shops.id')
            ->leftJoin('websites', 'webpages.website_id', '=', 'websites.id')
            ->defaultSort('-status')
            ->select([
                'webpages.id',
                'webpages.slug',
                'webpages.code',
                'webpages.title',
                'webpages.type',
                'organisations.slug as organisation_slug',
                'shops.slug as shop_slug',
                'websites.slug as website_slug',
                'speed.samples',
            ])
            ->selectRaw('ROUND(speed.lcp) as lcp')
            ->selectRaw('ROUND(speed.inp) as inp')
            ->selectRaw('ROUND(speed.cls::numeric, 3) as cls')
            ->selectRaw("$statusSql as status")
            ->allowedSorts(['code', 'samples', 'lcp', 'inp', 'cls', 'status'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function tableStructure(Website $website, ?string $prefix = null): Closure
    {
        return function (InertiaTable $table) use ($website, $prefix) {
            if ($prefix) {
                $table
                    ->name($prefix)
                    ->pageName($prefix.'Page');
            }

            foreach ($this->elementGroups($website) as $key => $elementGroup) {
                $table->elementGroup(
                    key: $key,
                    label: $elementGroup['label'],
                    elements: $elementGroup['elements'],
                    default: 'phone'
                );
            }

            $table
                ->withGlobalSearch()
                ->withLabelRecord([__('webpage'), __('webpages')])
                ->withEmptyState([
                    'title'       => __('No webpage has enough measured page loads yet'),
                    'description' => __('A webpage is listed once :count page loads have been measured in visitors\' browsers in the last :days days.', [
                        'count' => GetWebVitalsReport::MIN_SAMPLES,
                        'days'  => GetWebsitePageSpeedSummary::VISITOR_DAYS,
                    ]),
                ])
                ->column(key: 'type', label: '', icon: 'fal fa-shapes', tooltip: __('Type'), canBeHidden: false, type: 'icon')
                ->column(key: 'code', label: __('Code'), canBeHidden: false, sortable: true, searchable: true)
                ->column(key: 'title', label: __('Title'))
                ->column(key: 'status', label: __('Core Web Vitals'), tooltip: __('The worst of LCP, INP and CLS. Good means all three pass Google\'s limits'), sortable: true, tooltipIcon: true)
                ->column(key: 'lcp', label: 'LCP', tooltip: __('Largest Contentful Paint, 75th percentile: how long until the main content shows. Good is 2.5 s or less'), sortable: true, align: 'right', tooltipIcon: true)
                ->column(key: 'inp', label: 'INP', tooltip: __('Interaction to Next Paint, 75th percentile: how fast the page reacts to a tap or click. Good is 200 ms or less'), sortable: true, align: 'right', tooltipIcon: true)
                ->column(key: 'cls', label: 'CLS', tooltip: __('Cumulative Layout Shift, 75th percentile: how much the page jumps while loading. Good is 0.1 or less'), sortable: true, align: 'right', tooltipIcon: true)
                ->column(key: 'samples', label: __('Page loads'), tooltip: __('Page loads measured in visitors\' browsers in the last :days days', ['days' => GetWebsitePageSpeedSummary::VISITOR_DAYS]), sortable: true, align: 'right', tooltipIcon: true)
                ->defaultSort('-status');
        };
    }
}
