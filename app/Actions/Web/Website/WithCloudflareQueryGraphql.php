<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 09-12-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\Web\Website;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;

trait WithCloudflareQueryGraphql
{
    public function getZone($modelData): array
    {
        $since = Arr::get($modelData, 'since') ?? Date::now()->subDay()->toIso8601String();
        $until = Arr::get($modelData, 'until') ?? Date::now()->toIso8601String();
        $zoneTag = Arr::get($modelData, 'zoneTag');

        $timeField = 'datetime';
        $groupsFunction = 'httpRequests1hGroups';
        $orderBy = 'datetime_ASC';

        if ($this->isDate($since) && $this->isDate($until)) {
            $timeField = 'date';
            $groupsFunction = 'httpRequests1dGroups';
            $orderBy = 'date_ASC';
        }
        $query = <<<GQL
            query Viewer {
                viewer {
                    zones(filter: { zoneTag: "$zoneTag" }) {
                        totals: $groupsFunction(
                            limit: 10000
                            filter: { {$timeField}_geq: "$since", {$timeField}_lt: "$until" }
                        ) {
                            uniq {
                                uniques
                            }
                        }
                        zones: $groupsFunction(
                            orderBy: [$orderBy]
                            limit: 10000
                            filter: { {$timeField}_geq: "$since", {$timeField}_lt: "$until" }
                        ) {
                            dimensions {
                                timeslot: $timeField
                            }
                            uniq {
                                uniques
                            }
                            sum {
                                browserMap {
                                    pageViews
                                    key: uaBrowserFamily
                                }
                                pageViews
                                requests
                            }
                        }
                    }
                }
            }
        GQL;

        return $this->getCloudflareAnalytics($modelData, $query);
    }

    public function getRumAnalyticsTopNs($modelData): array
    {
        $show = Arr::get($modelData, 'showTopNs') ?? 'visits';
        $siteTag = Arr::get($modelData, 'siteTag');
        $accountTag = Arr::get($modelData, 'accountTag');
        $since = Arr::get($modelData, 'since') ?? Date::now()->subDay()->toIso8601String();
        $until = Arr::get($modelData, 'until') ?? Date::now()->toIso8601String();

        if (! $siteTag || ! $accountTag) {
            return [];
        }

        $order = $show === 'visits' ? 'sum_visits_DESC' : 'count_DESC';

        $filter = <<<FILTER
            {
                AND: [
                    { datetime_geq: "$since", datetime_leq: "$until" },
                    { OR: [{ siteTag: "$siteTag" }] },
                    { bot: 0 }
                ]
            }
        FILTER;

        $dimensions = [
            'topReferers' => 'refererHost',
            'topPaths' => 'requestPath',
            'topHosts' => 'requestHost',
            'topBrowsers' => 'userAgentBrowser',
            'topOSs' => 'userAgentOS',
            'topDeviceTypes' => 'deviceType',
            'countries' => 'countryName',
        ];

        $dimensionsQuery = implode("\n", array_map(function ($key, $value) use ($filter, $order) {
            return <<<GQL
            $key: rumPageloadEventsAdaptiveGroups(
                filter: $filter, 
                limit: 15, 
                orderBy: [$order]
            ) {
                count
                avg {
                    sampleInterval
                }
                sum {
                    visits
                }
                dimensions {
                    metric: $value
                }
            }
            GQL;
        }, array_keys($dimensions), $dimensions));

        $query = <<<GQL
            query Viewer {
                viewer {
                    accounts(filter: { accountTag: "$accountTag" }) {
                        total: rumPageloadEventsAdaptiveGroups(
                            filter: $filter, 
                            limit: 1
                        ) {
                            count
                            sum {
                                visits
                            }
                        }
                        $dimensionsQuery
                    }
                }
            }
        GQL;

        return $this->getCloudflareAnalytics($modelData, $query);
    }

    public function getRumPerfAnalyticsTopNs($modelData): array
    {
        $pageLoadFilterVal = ucfirst(Arr::get($modelData, 'filter') ?? 'P50');
        $pageLoadAggregation = $pageLoadFilterVal === 'Avg' ? 'avg' : 'quantiles';
        $pageLoadFilterVal = $pageLoadAggregation === 'avg' ? '' : $pageLoadFilterVal;

        $siteTag = Arr::get($modelData, 'siteTag');
        $accountTag = Arr::get($modelData, 'accountTag');
        $since = Arr::get($modelData, 'since') ?? Date::now()->subDay()->toIso8601String();
        $until = Arr::get($modelData, 'until') ?? Date::now()->toIso8601String();

        if (! $siteTag || ! $accountTag) {
            return [];
        }

        $order = 'count_DESC';

        $filter = <<<FILTER
            {
            AND: [
                { datetime_geq: "$since", datetime_leq: "$until" },
                { OR: [{ siteTag: "$siteTag" }] },
                { bot: 0 }
            ]
            }
        FILTER;

        $metrics = [
            'pageLoadTime',
            'dnsTime',
            'connectionTime',
            'requestTime',
            'responseTime',
            'pageRenderTime',
            'loadEventTime',
            'firstPaint',
            'firstContentfulPaint',
        ];

        $metricsQuery = implode("\n", array_map(function ($metric) use ($pageLoadFilterVal) {
            return "$metric: $metric$pageLoadFilterVal";
        }, $metrics));

        $dimensions = [
            'countries' => 'countryName',
            'topReferers' => 'refererHost',
            'topPaths' => 'requestPath',
            'topHosts' => 'requestHost',
            'topBrowsers' => 'userAgentBrowser',
            'topOSs' => 'userAgentOS',
            'topDeviceTypes' => 'deviceType',
        ];

        $dimensionsQuery = implode("\n", array_map(function ($key, $value) use ($filter, $order, $pageLoadAggregation, $pageLoadFilterVal) {
            return <<<GQL
            $key: rumPerformanceEventsAdaptiveGroups(
                filter: $filter
                limit: 15
                orderBy: [$order]
            ) {
                count
                avg {
                sampleInterval
                }
                aggregation: $pageLoadAggregation {
                pageLoadTime: pageLoadTime$pageLoadFilterVal
                }
                dimensions {
                metric: $value
                }
            }
            GQL;
        }, array_keys($dimensions), $dimensions));

        $query = <<<GQL
        query Viewer {
            viewer {
                accounts(filter: { accountTag: "$accountTag" }) {
                    total: rumPerformanceEventsAdaptiveGroups(filter: $filter, limit: 1) {
                        count
                        aggregation: $pageLoadAggregation {
                            $metricsQuery
                        }
                    }
                    series: rumPerformanceEventsAdaptiveGroups(limit: 5000, filter: $filter) {
                    dimensions {
                        {$this->getDimensionTime($since, $until)}
                    }
                    count
                        aggregation: $pageLoadAggregation {
                            $metricsQuery
                        }
                    }
                    $dimensionsQuery
                }
            }
        }
        GQL;

        return $this->getCloudflareAnalytics($modelData, $query);

    }

    private function getDimensionTime($since, $until)
    {
        return $this->isDate($since) && $this->isDate($until) ? 'datetimeHour' : 'datetimeFifteenMinutes';
    }

    public function getRumSparkline($modelData): array
    {
        $siteTag = Arr::get($modelData, 'siteTag');
        $accountTag = Arr::get($modelData, 'accountTag');
        $since = Arr::get($modelData, 'since') ?? Date::now()->subDay()->toIso8601String();
        $until = Arr::get($modelData, 'until') ?? Date::now()->toIso8601String();

        if (! $siteTag || ! $accountTag) {
            return [];
        }

        $filter = <<<FILTER
        {
            AND: [
                { datetime_geq: "$since", datetime_leq: "$until" },
                { OR: [{ siteTag: "$siteTag" }] },
                { bot: 0 }
            ]
        }
        FILTER;

        $metrics = [
            'visits' => 'rumPageloadEventsAdaptiveGroups',
            'pageviews' => 'rumPageloadEventsAdaptiveGroups',
            'performance' => 'rumPerformanceEventsAdaptiveGroups',
            'totalPerformance' => 'rumPerformanceEventsAdaptiveGroups',
            'lcp' => 'rumWebVitalsEventsAdaptiveGroups',
            'inp' => 'rumWebVitalsEventsAdaptiveGroups',
            'fid' => 'rumWebVitalsEventsAdaptiveGroups',
            'cls' => 'rumWebVitalsEventsAdaptiveGroups',
            'visitsDelta' => 'rumPageloadEventsAdaptiveGroups',
            'pageviewsDelta' => 'rumPageloadEventsAdaptiveGroups',
            'performanceDelta' => 'rumPerformanceEventsAdaptiveGroups',
        ];

        $metricsQuery = implode("\n", array_map(function ($key, $value) use ($filter, $since, $until) {
            $aggregation = $key === 'performance' || $key === 'totalPerformance' || $key === 'performanceDelta' ? 'quantiles { pageLoadTime: pageLoadTimeP50 }' : '';
            $sum = in_array($key, ['visits', 'visitsDelta']) ? 'sum { visits }' : '';
            $count = in_array($key, ['pageviews', 'pageviewsDelta', 'performanceDelta']) ? 'count' : '';
            $avg = 'avg { sampleInterval }';
            $dimensions = "dimensions { ts: {$this->getDimensionTime($since, $until)} }";

            return <<<GQL
            $key: $value(limit: 5000, filter: $filter) {
                $count
                $sum
                $avg
                $aggregation
                $dimensions
            }
            GQL;
        }, array_keys($metrics), $metrics));

        $query = <<<GQL
        query Viewer {
            viewer {
                accounts(filter: {accountTag: "$accountTag"}) {
                    $metricsQuery
                }
            }
        }
        GQL;

        return $this->getCloudflareAnalytics($modelData, $query);
    }

    public function getRumAnalyticsTimeseries($modelData): array
    {

        $getMetric = [
            'all' => '',
            'referer' => 'refererHost',
            'host' => 'requestHost',
            'country' => 'countryName',
            'path' => 'requestPath',
            'browser' => 'userAgentBrowser',
            'os' => 'userAgentOS',
            'deviceType' => 'deviceType',
        ];
        $metricFilter = Arr::get($modelData, 'filter') ?? 'all';
        $metric = $getMetric[$metricFilter] != '' ? "metric: {$getMetric[$metricFilter]}" : '';

        $filterData = Arr::get($modelData, 'filterData') ?? '';
        if ($filterData) {
            $data = explode(',', trim($filterData));
            $filterData = '{ OR: [';
            foreach ($data as $d) {
                $filterData .= "{ {$getMetric[$metricFilter]}: \"$d\" },";
            }
            $filterData = rtrim($filterData, ',');
            $filterData .= '] }';
        }

        $siteTag = Arr::get($modelData, 'siteTag');
        $accountTag = Arr::get($modelData, 'accountTag');
        $since = Arr::get($modelData, 'since') ?? Date::now()->subDay()->toIso8601String();
        $until = Arr::get($modelData, 'until') ?? Date::now()->toIso8601String();

        if (! $siteTag || ! $accountTag) {
            return [];
        }

        $filter = <<<FILTER
        {
            AND: [
                { datetime_geq: "$since", datetime_leq: "$until" },
                { OR: [{ siteTag: "$siteTag" }] },
                { bot: 0 }
                $filterData
            ]
        }
        FILTER;

        $query = <<<GQL
        query Viewer {
            viewer {
                accounts(filter: { accountTag: "$accountTag" }) {
                    series: rumPageloadEventsAdaptiveGroups(limit: 5000, filter: $filter) {
                        count
                        avg {
                            sampleInterval
                        }
                        sum {
                            visits
                        }
                        dimensions {
                            ts: {$this->getDimensionTime($since, $until)}
                            $metric
                        }
                    }
                }
            }
        }
        GQL;

        return $this->getCloudflareAnalytics($modelData, $query);
    }

    public function getRumWebVitalsTop($modelData): ?array
    {
        $siteTag = Arr::get($modelData, 'siteTag');
        $accountTag = Arr::get($modelData, 'accountTag');
        $since = Arr::get($modelData, 'since') ?? Date::now()->subDay()->toIso8601String();
        $until = Arr::get($modelData, 'until') ?? Date::now()->toIso8601String();

        if (! $siteTag || ! $accountTag) {
            return null;
        }

        $filter = <<<FILTER
        {
            AND: [
                { datetime_geq: "$since", datetime_leq: "$until" },
                { OR: [{ siteTag: "$siteTag" }] },
                { bot: 0 }
            ]
        }
        FILTER;

        $debugFilters = [
            'lcp' => '{ largestContentfulPaint_neq: -1, largestContentfulPaint_gt: 2500000, largestContentfulPaintElement_neq: "", largestContentfulPaintObjectHost_neq: "", largestContentfulPaintObjectPath_neq: "" }',
            'fid' => '{ firstInputDelay_neq: -1, firstInputDelay_gt: 100000, firstInputDelayElement_neq: "", firstInputDelayName_neq: "" }',
            'cls' => '{ cumulativeLayoutShift_neq: -1, cumulativeLayoutShift_gt: 0.1, cumulativeLayoutShiftElement_neq: "" }',
        ];

        $metrics = ['lcp', 'inp', 'fid', 'cls'];
        $queries = [];

        foreach ($metrics as $metric) {
            $queries[] = <<<GQL
            $metric: rumWebVitalsEventsAdaptiveGroups(filter: $filter, limit: 1) {
                count
                sum {
                    {$metric}Total
                    {$metric}Good
                    {$metric}NeedsImprovement
                    {$metric}Poor
                }
                avg {
                    sampleInterval
                }
            }
            GQL;

            if (isset($debugFilters[$metric])) {
                $queries[] = <<<GQL
                {$metric}DebugView: rumWebVitalsEventsAdaptiveGroups(filter: {$debugFilters[$metric]}, orderBy: [count_DESC], limit: 15) {
                    sum {
                        {$metric}Total
                        {$metric}Good
                        {$metric}NeedsImprovement
                        {$metric}Poor
                    }
                    dimensions {
                        {$metric}Element
                        requestScheme
                        requestHost
                        requestPath
                    }
                    aggregation: quantiles {
                        {$metric}P50
                        {$metric}P75
                        {$metric}P90
                        {$metric}P99
                    }
                }
                GQL;
            }
        }

        $query = <<<GQL
        query Viewer {
            viewer {
                accounts(filter: {accountTag: "$accountTag"}) {
                    total: rumWebVitalsEventsAdaptiveGroups(filter: $filter, limit: 1) {
                        count
                        aggregation: quantiles {
                            largestContentfulPaintP50
                            largestContentfulPaintP75
                            largestContentfulPaintP90
                            largestContentfulPaintP99
                            interactionToNextPaintP50
                            interactionToNextPaintP75
                            interactionToNextPaintP90
                            interactionToNextPaintP99
                            firstInputDelayP50
                            firstInputDelayP75
                            firstInputDelayP90
                            firstInputDelayP99
                            cumulativeLayoutShiftP50
                            cumulativeLayoutShiftP75
                            cumulativeLayoutShiftP90
                            cumulativeLayoutShiftP99
                        }
                    }
                    lcpSeries: rumWebVitalsEventsAdaptiveGroups(filter: $filter, limit: 5000) {
                        dimensions {
                            {$this->getDimensionTime($since, $until)}
                        }
                        count
                        aggregation: quantiles {
                            largestContentfulPaintP50
                            largestContentfulPaintP75
                            largestContentfulPaintP90
                            largestContentfulPaintP99
                        }
                    }
                    {$queries[0]}
                    {$queries[1]}
                    {$queries[2]}
                    {$queries[3]}
                    {$queries[4]}
                    {$queries[5]}
                    {$queries[6]}
                    {$queries[7]}
                }
            }
        }
        GQL;

        return $this->getCloudflareAnalytics($modelData, $query);
    }

    public function getRumWebVitals($modelData): ?array
    {

        $sectionDimension = [
            'lcp' => 'largestContentfulPaintPath',
            'inp' => '',
            'fid' => 'firstInputDelayPath',
            'cls' => 'cumulativeLayoutShiftPath',
        ];

        $filterDataDimensionMap = [
            'url' => 'requestHost',
            'browser' => 'userAgentBrowser',
            'os' => 'userAgentOS',
            'country' => 'countryName',
            'element' => 'largestContentfulPaintElement',
        ];

        $section = Arr::get($modelData, 'section') ?? 'lcp';
        $filterData = Arr::get($modelData, 'filterData');

        if ($section === 'inp') {
            if ($filterData == 'element') {
                $filterData = 'country';
            } else {
                $filterData = $filterData ?? 'browser';
            }
        }
        $filterData = $filterData ?? 'url';
        $siteTag = Arr::get($modelData, 'siteTag');
        $accountTag = Arr::get($modelData, 'accountTag');
        $since = Arr::get($modelData, 'since') ?? Date::now()->subDay()->toIso8601String();
        $until = Arr::get($modelData, 'until') ?? Date::now()->toIso8601String();

        if (! $siteTag || ! $accountTag) {
            return null;
        }

        $filter = <<<FILTER
        {
            AND: [
                { datetime_geq: "$since", datetime_leq: "$until" },
                { OR: [{ siteTag: "$siteTag" }] },
                { bot: 0 }
            ]
        }
        FILTER;

        $query = <<<GQL
        query Viewer {
            viewer {
                accounts(filter: {accountTag: "$accountTag"}) {
                    rumWebVitalsEventsAdaptiveGroups(filter: $filter, limit: 15, orderBy: [sum_{$section}Total_DESC]) {
                        count
                        sum {
                            {$section}Total
                            {$section}Good
                            {$section}NeedsImprovement
                            {$section}Poor
                        }
                        dimensions {
                            {$sectionDimension[$section]}
                            {$filterDataDimensionMap[$filterData]}
                        }
                        avg {
                            sampleInterval
                        }
                    }
                }
            }
        }
        GQL;

        return $this->getCloudflareAnalytics($modelData, $query);
    }

    private function getCloudflareAnalytics(array $modelData, string $query, $try = 3): ?array
    {
        $apiToken = Arr::get($modelData, 'apiToken');

        $urlCloudflareGraphql = 'https://api.cloudflare.com/client/v4/graphql';
        if ($try == 0) {
            return [];
        }

        try {
            $response = Http::timeout(10)->withHeaders([
                'Authorization' => "Bearer $apiToken",
                'Content-Type' => 'application/json',
            ])->post($urlCloudflareGraphql, [
                'query' => $query,
            ]);
        } catch (Exception $e) {
            if (str_contains($e->getMessage(), 'Resolving timed out')) {
                return $this->getCloudflareAnalytics($modelData, $query, --$try);
            }
            throw $e;
        }

        return $response->json();
    }
}
