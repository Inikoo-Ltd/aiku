<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 01 Nov 2026 23:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Web\Website\UI;

use App\Models\SysAdmin\Group;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Varnish on each web box (boro, litio), as the server-metrics sampler posts it every minute, beside the
 * storefront page views browsers report. Rates are per minute whatever the bucket, so the ranges compare.
 */
class GetWebsitesVarnishMetrics
{
    use AsAction;

    /** range => [window minutes, bucket minutes] */
    public const array RANGES = [
        '1h'  => [60, 1],
        '6h'  => [360, 5],
        '24h' => [1440, 15],
        '7d'  => [10080, 60],
        '30d' => [43200, 360],
    ];

    /**
     * @return array{range: string, ranges: string[], buckets: string[], hosts: array<string, array{hits: array<int, float|null>, misses: array<int, float|null>, memory_mb: array<int, float|null>}>, page_views: array<int, float|null>, releases: array<int, array{index: int, label: string}>}
     */
    public function handle(Group $group, ?string $range): array
    {
        $range                   = array_key_exists((string) $range, self::RANGES) ? $range : '1h';
        [$windowMinutes, $step]  = self::RANGES[$range];
        $seconds                 = $step * 60;
        $last                    = intdiv(now()->timestamp, $seconds) * $seconds;
        $first                   = $last - ($windowMinutes / $step - 1) * $seconds;
        $since                   = Carbon::createFromTimestamp($first);
        $bucket                  = "date_bin(interval '$step minutes', %s, timestamptz '2000-01-01')";
        $epochs                  = range($first, $last, $seconds);
        $indexOf                 = array_flip($epochs);
        $empty                   = array_fill(0, count($epochs), null);

        $rows = $step < 60
            ? DB::table('server_metrics')->join('servers', 'servers.id', 'server_metrics.server_id')
                ->where('recorded_at', '>=', $since)->whereNotNull('varnish_hits')
                ->selectRaw('servers.slug, extract(epoch from '.sprintf($bucket, 'recorded_at').')::bigint as epoch, count(*) as minutes,
                    sum(varnish_hits) as hits, sum(varnish_misses) as misses, avg(varnish_memory_mb) as memory_mb')
            : DB::table('server_metric_hours')->join('servers', 'servers.id', 'server_metric_hours.server_id')
                ->where('hour', '>=', $since)->whereNotNull('varnish_hits')
                ->selectRaw('servers.slug, extract(epoch from '.sprintf($bucket, 'hour').')::bigint as epoch, sum(samples) as minutes,
                    sum(varnish_hits) as hits, sum(varnish_misses) as misses, sum(varnish_memory_avg * samples) / sum(samples) as memory_mb');

        $hosts = [];
        foreach ($rows->groupBy('servers.slug', 'epoch')->orderBy('servers.slug')->get() as $row) {
            if (!isset($indexOf[$row->epoch])) {
                continue;
            }
            $hosts[$row->slug] ??= ['hits' => $empty, 'misses' => $empty, 'memory_mb' => $empty];
            $index                                = $indexOf[$row->epoch];
            $hosts[$row->slug]['hits'][$index]      = round($row->hits / $row->minutes, 1);
            $hosts[$row->slug]['misses'][$index]    = round($row->misses / $row->minutes, 1);
            $hosts[$row->slug]['memory_mb'][$index] = round((float) $row->memory_mb, 1);
        }

        $pageViews = $empty;
        $views     = DB::table('website_page_views')->where('group_id', $group->id)
            ->where('view_date', '>=', $since->toDateString())->where('created_at', '>=', $since)
            ->selectRaw('extract(epoch from '.sprintf($bucket, 'created_at').')::bigint as epoch, count(*) as views')
            ->groupBy('epoch')->get();
        foreach ($views as $row) {
            if (isset($indexOf[$row->epoch])) {
                $pageViews[$indexOf[$row->epoch]] = round($row->views / $step, 1);
            }
        }

        $releases = $step >= 60 ? [] : DB::table('app_deployments')->where('created_at', '>=', $since)->orderBy('created_at')
            ->get(['created_at', 'semantic_version'])
            ->map(fn ($deployment) => [
                'index' => intdiv(Carbon::parse($deployment->created_at, 'UTC')->timestamp - $first, $seconds),
                'label' => (string) $deployment->semantic_version,
            ])->filter(fn (array $release) => $release['index'] < count($epochs))->values()->all();

        return [
            'range'      => $range,
            'ranges'     => array_keys(self::RANGES),
            'buckets'    => array_map(fn (int $epoch) => Carbon::createFromTimestamp($epoch)->toIso8601ZuluString(), $epochs),
            'hosts'      => $hosts,
            'page_views' => $pageViews,
            'releases'   => $releases,
        ];
    }
}
