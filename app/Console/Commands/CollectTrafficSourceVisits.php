<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Console\Commands;

use App\Enums\CRM\TrafficSource\TrafficSourcesTypeEnum;
use App\Models\CRM\TrafficSource;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CollectTrafficSourceVisits extends Command
{
    protected $signature = 'traffic-source:collect-visits
                           {--days=2 : How many days back to fold in}
                           {--forget-today : Clear today\'s counters and rows, then start counting again from now}';

    protected $description = 'Fold the per-day visit counters written at capture into traffic_source_visits';

    /**
     * Throws away today's counting and starts again from now.
     *
     * Needed after a change to what counts as a visit: the counters are cumulative for the day, so the
     * old rule's numbers survive a deploy and keep being written into the table until midnight. Clears
     * the cache counters and today's rows together, or the next run would restore what it just deleted.
     */
    private function forgetToday(): void
    {
        $date = now()->toDateString();

        foreach (TrafficSource::select('id', 'shop_id', 'type')->get() as $source) {
            Cache::forget('traffic_visits:'.$date.':'.$source->shop_id.':'.$source->type);
        }

        $deleted = DB::table('traffic_source_visits')->where('date', $date)->delete();

        $this->warn("Forgot today's visit counting: {$deleted} row(s) removed, counters cleared.");
    }

    /**
     * Visits are counted in the cache on the storefront hot path, where a database write per page view
     * would be indefensible. This moves them into a table so they survive the counters' 8-day expiry
     * and can be reported over any period.
     *
     * Safe to rerun: each shop, channel and day is a single upserted row, written from the counter's
     * current value rather than added to.
     */
    public function handle(): int
    {
        if ($this->option('forget-today')) {
            $this->forgetToday();
        }

        $sources = TrafficSource::select('id', 'shop_id', 'type')->get()
            ->keyBy(fn (TrafficSource $source) => $source->shop_id.':'.$source->type);

        $written = 0;

        foreach (range((int) $this->option('days') - 1, 0) as $daysAgo) {
            $date = now()->subDays($daysAgo)->toDateString();

            foreach ($sources as $key => $source) {
                [$shopId, $type] = explode(':', $key, 2);

                $visits = (int) Cache::get('traffic_visits:'.$date.':'.$shopId.':'.$type, 0);

                if ($visits === 0) {
                    continue;
                }

                DB::table('traffic_source_visits')->updateOrInsert(
                    ['traffic_source_id' => $source->id, 'date' => $date],
                    ['shop_id' => $source->shop_id, 'visits' => $visits, 'updated_at' => now(), 'created_at' => now()]
                );

                $written++;
            }
        }

        $this->info("Wrote {$written} visit row(s) across ".count(TrafficSourcesTypeEnum::cases()).' channel types.');

        return Command::SUCCESS;
    }
}
