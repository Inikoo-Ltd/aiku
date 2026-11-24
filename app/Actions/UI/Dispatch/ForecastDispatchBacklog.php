<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Dispatch;

use App\Models\Inventory\Warehouse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The backlog of the next four weeks, of all channels, of wholesale with partners and marketplaces,
 * and of dropshipping, from yesterday's closing backlog: each day's new and closed (dispatched or
 * cancelled) delivery notes are the same weekday last year, scaled by the last four weeks against the
 * same four weeks last year, so the autumn ramp and the Christmas closure come from last year. The
 * band holds 80% of how far that projection missed over the last twelve weeks, day by day ahead.
 * Backtested on warehouse ED with weekly origins from Sep to Nov 2026, it missed the backlog change by
 * 128 delivery notes on average three weeks ahead and 167 four weeks ahead (it doubles from six
 * weeks), and TimesFM on the daily series by 259, running 8% to 13% short of new delivery notes in
 * October and November. Delivery notes have real timing only since Aug 2025 (Aurora created them on
 * the day they left).
 */
class ForecastDispatchBacklog
{
    use AsObject;

    public const int DAYS_AHEAD = 28;

    private const int TREND_DAYS = 28;

    private const int BAND_WEEKS = 12;

    private const float BAND_SHARE = 0.8;

    public const array CHANNELS = ['b2b', 'dropshipping'];

    private const int HISTORY_DAYS = 364 + self::TREND_DAYS + self::DAYS_AHEAD + 7 * (self::BAND_WEEKS - 1);

    /**
     * @param  array<string, int>  $backlogYesterday  channel => backlog at the end of yesterday
     *
     * @return array<string, list<array{date: string, backlog: int, low: int, high: int}>> "all" and each channel => today first, none without last year's history
     */
    public function handle(Warehouse $warehouse, array $backlogYesterday): array
    {
        $timezone = $warehouse->organisation->timezone?->name ?? 'UTC';
        $today    = now($timezone)->startOfDay();
        $counts   = $this->dailyCounts($warehouse, $today->copy()->subDays(self::HISTORY_DAYS), $today, $timezone);

        $all = ['created' => array_fill(0, self::HISTORY_DAYS, 0), 'closed' => array_fill(0, self::HISTORY_DAYS, 0)];
        foreach ($counts as $channelCounts) {
            foreach ($channelCounts as $kind => $days) {
                foreach ($days as $day => $total) {
                    $all[$kind][$day] += $total;
                }
            }
        }
        $series = ['all' => $all] + array_intersect_key($counts, array_flip(self::CHANNELS));

        $forecast = [];
        foreach ($series as $channel => ['created' => $created, 'closed' => $closed]) {
            $backlog = $channel === 'all' ? array_sum($backlogYesterday) : ($backlogYesterday[$channel] ?? 0);
            foreach ($this->projection($created, $closed, $backlog) as $ahead => $day) {
                $forecast[$channel][] = ['date' => $today->copy()->addDays($ahead)->toDateString()] + $day;
            }
        }

        return $forecast;
    }

    /**
     * @param  list<int>  $created  new delivery notes per day up to yesterday, oldest first
     * @param  list<int>  $closed  dispatched and cancelled delivery notes per day, same days
     *
     * @return list<array{backlog: int, low: int, high: int}> today first
     */
    public function projection(array $created, array $closed, int $backlogYesterday): array
    {
        $today = count($created);
        $ahead = $this->changesFrom($created, $closed, $today);
        if ($ahead === null) {
            return [];
        }

        $misses = array_fill(0, self::DAYS_AHEAD, []);
        for ($week = 0; $week < self::BAND_WEEKS; $week++) {
            $origin = $today - self::DAYS_AHEAD - 7 * $week;
            $past   = $this->changesFrom($created, $closed, $origin);
            if ($past === null) {
                continue;
            }
            $projected = $actual = 0.0;
            foreach ($past as $day => $change) {
                $projected      += $change;
                $actual         += $created[$origin + $day] - $closed[$origin + $day];
                $misses[$day][] = abs($projected - $actual);
            }
        }

        $backlog = (float) $backlogYesterday;
        $days    = [];
        foreach ($ahead as $day => $change) {
            $backlog = max(0.0, $backlog + $change);
            $spread  = $this->share($misses[$day]);
            $days[]  = ['backlog' => (int) round($backlog), 'low' => (int) round(max(0.0, $backlog - $spread)), 'high' => (int) round($backlog + $spread)];
        }

        return $days;
    }

    /**
     * The backlog change of each day from $origin on, or null without four weeks of last year behind it.
     *
     * @return list<float>|null
     */
    private function changesFrom(array $created, array $closed, int $origin): ?array
    {
        if ($origin - 364 - self::TREND_DAYS < 0) {
            return null;
        }

        $trend = [];
        foreach (['created' => $created, 'closed' => $closed] as $key => $series) {
            $lastYear = array_sum(array_slice($series, $origin - 364 - self::TREND_DAYS, self::TREND_DAYS));
            if ($lastYear <= 0) {
                return null;
            }
            $trend[$key] = array_sum(array_slice($series, $origin - self::TREND_DAYS, self::TREND_DAYS)) / $lastYear;
        }

        $changes = [];
        for ($day = 0; $day < self::DAYS_AHEAD; $day++) {
            $changes[] = $created[$origin + $day - 364] * $trend['created'] - $closed[$origin + $day - 364] * $trend['closed'];
        }

        return $changes;
    }

    private function share(array $misses): float
    {
        if (!$misses) {
            return 0.0;
        }
        sort($misses);

        return $misses[(int) ceil(self::BAND_SHARE * count($misses)) - 1];
    }

    /**
     * @return array<string, array{created: list<int>, closed: list<int>}> channel => new and closed delivery notes per local day, from $from up to the day before $today
     */
    private function dailyCounts(Warehouse $warehouse, Carbon $from, Carbon $today, string $timezone): array
    {
        $zone  = DB::getPdo()->quote($timezone);
        $start = $from->copy()->utc();
        $end   = $today->copy()->utc();

        $deliveryNotes = DB::table('delivery_notes')
            ->join('shops', 'delivery_notes.shop_id', '=', 'shops.id')
            ->where('delivery_notes.warehouse_id', $warehouse->id)
            ->whereNull('delivery_notes.deleted_at')
            ->where('delivery_notes.created_at', '>=', $start->copy()->subDays(180))
            ->where('delivery_notes.created_at', '<', $end)
            ->selectRaw('delivery_notes.created_at, '.GetDispatchBacklogHistory::CLOSED_AT_SQL.' as closed_at, '.GetDispatchBacklogHistory::channelSql().' as channel');

        $rows = DB::query()->fromSub($deliveryNotes, 'dn')
            ->selectRaw("channel, to_char(created_at at time zone $zone, 'YYYY-MM-DD') as day, count(*) as total, 'created' as kind")
            ->where('created_at', '>=', $start)
            ->groupBy('channel', 'day')
            ->unionAll(
                DB::query()->fromSub($deliveryNotes, 'dn')
                    ->selectRaw("channel, to_char(closed_at at time zone $zone, 'YYYY-MM-DD') as day, count(*) as total, 'closed' as kind")
                    ->where('closed_at', '>=', $start)
                    ->where('closed_at', '<', $end)
                    ->groupBy('channel', 'day')
            )
            ->get();

        $empty  = array_fill(0, self::HISTORY_DAYS, 0);
        $counts = [];
        foreach ($rows as $row) {
            $offset = (int) $from->diffInDays(Carbon::parse($row->day, $timezone));
            if ($offset >= 0 && $offset < self::HISTORY_DAYS) {
                $counts[$row->channel] ??= ['created' => $empty, 'closed' => $empty];
                $counts[$row->channel][$row->kind][$offset] = (int) $row->total;
            }
        }

        return $counts;
    }
}
