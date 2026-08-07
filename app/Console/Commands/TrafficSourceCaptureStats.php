<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class TrafficSourceCaptureStats extends Command
{
    protected $signature = 'traffic-source:capture-stats {--days=3 : How many days back to report}';

    protected $description = 'Report how often storefront first hits identified a traffic source, and how often they could not';

    /**
     * Answers the question a registration count cannot: when a visitor arrives with no touch, is that
     * because they came direct, or because capture failed to recognise where they came from. Counters
     * are written by CaptureTrafficSource and expire after 8 days.
     */
    public function handle(): int
    {
        $outcomes = ['matched', 'repeat', 'direct', 'unmatched'];
        $rows     = [];

        foreach (range((int) $this->option('days') - 1, 0) as $daysAgo) {
            $day    = now()->subDays($daysAgo)->toDateString();
            $counts = [];

            foreach ($outcomes as $outcome) {
                $counts[$outcome] = (int) Cache::get('traffic_capture:'.$day.':'.$outcome, 0);
            }

            $total      = array_sum($counts);
            $identified = $counts['matched'] + $counts['repeat'];

            $rows[] = [
                $day,
                $total,
                $counts['matched'],
                $counts['repeat'],
                $counts['direct'],
                $counts['unmatched'],
                $total > 0 ? round($identified / $total * 100, 1).'%' : '-',
            ];
        }

        $this->table(['Day', 'Hits', 'Matched', 'Repeat', 'Direct', 'Unmatched', 'Identified'], $rows);

        $hosts = Cache::get('traffic_capture:'.now()->toDateString().':hosts', []);

        if ($hosts === []) {
            $this->info('No unrecognised referrers recorded today.');

            return Command::SUCCESS;
        }

        arsort($hosts);

        $this->newLine();
        $this->info('Unrecognised referrers today (a source worth mapping would show up here):');
        $this->table(
            ['Host', 'Hits'],
            collect($hosts)->take(20)->map(fn (int $count, string $host) => [$host, $count])->values()->all()
        );

        return Command::SUCCESS;
    }
}
