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
        $outcomes = ['matched', 'repeat', 'direct', 'unmatched', 'internal'];
        $rows     = [];

        foreach (range((int) $this->option('days') - 1, 0) as $daysAgo) {
            $day = now()->subDays($daysAgo)->toDateString();

            /* Anonymous first, since that is the row that answers "do new visitors arrive with a
               source". The logged-in row is mostly regulars returning direct and says little. */
            foreach (['anon', 'auth'] as $audience) {
                $counts = [];

                foreach ($outcomes as $outcome) {
                    $counts[$outcome] = (int) Cache::get('traffic_capture:'.$day.':'.$audience.':'.$outcome, 0);
                }

                /* Identified is a share of arrivals, never of page views. `internal` is a visitor
                   browsing our own pages after arriving, so leaving it in the denominator let one
                   session dilute its own arrival a dozen times over - a healthy day read as 7%
                   identified and raised a false alarm. */
                $arrivals   = array_sum($counts) - $counts['internal'];
                $identified = $counts['matched'] + $counts['repeat'];

                $rows[] = [
                    $day,
                    $audience === 'anon' ? 'anonymous' : 'logged in',
                    $arrivals,
                    $counts['matched'],
                    $counts['repeat'],
                    $counts['direct'],
                    $counts['unmatched'],
                    $counts['internal'],
                    $arrivals > 0 ? round($identified / $arrivals * 100, 1).'%' : '-',
                ];
            }
        }

        $this->table(['Day', 'Visitor', 'Arrivals', 'Matched', 'Repeat', 'Direct', 'Unmatched', 'Browsing', 'Identified'], $rows);
        $this->line('Arrivals exclude Browsing (own-site page views). Counters written before this split still hold browsing inside Direct.');

        $hosts = Cache::get('traffic_capture:'.now()->toDateString().':hosts', []);

        if ($hosts === []) {
            $this->info('No unrecognised referrers recorded today (external ones now record as referral touches).');

            return Command::SUCCESS;
        }

        arsort($hosts);

        $this->newLine();
        $this->info('Referrers rejected today (malformed hosts and our own systems; real ones are recorded as referral touches):');
        $this->table(
            ['Host', 'Hits'],
            collect($hosts)->take(20)->map(fn (int $count, string $host) => [$host, $count])->values()->all()
        );

        return Command::SUCCESS;
    }
}
