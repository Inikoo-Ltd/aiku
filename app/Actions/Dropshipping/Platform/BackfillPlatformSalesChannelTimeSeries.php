<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 29 Aug 2026 18:00:00 Central European Summer Time, Bratislava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Platform;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\Platform;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Backfills platform_sales_channel_time_series_records for manual platforms by dispatching
 * ProcessPlatformTimeSeriesRecords over each shop's full invoice history. The processor expands
 * every window to full periods, so no partial-window sums can be stored. Dry run unless --commit
 * is given.
 */
class BackfillPlatformSalesChannelTimeSeries
{
    use AsAction;

    public string $commandSignature = 'platforms:backfill_sales_channel_time_series {--commit : Dispatch the jobs instead of only reporting} {--from= : Start date (Y-m-d), defaults to each shop\'s first invoice}';

    public function asCommand(Command $command): int
    {
        $commit = (bool) $command->option('commit');
        $from   = $command->option('from');

        $rows = [];
        $jobs = 0;

        foreach (Platform::where('type', PlatformTypeEnum::MANUAL)->get() as $platform) {
            $shops = DB::connection('aiku_no_sticky')->table('invoices')
                ->where('platform_id', $platform->id)
                ->whereNull('deleted_at')
                ->groupBy('shop_id')
                ->select('shop_id', DB::raw('min(date) as first_date'), DB::raw('count(*) as invoice_count'))
                ->get();

            foreach ($shops as $shop) {
                $windowFrom = $from ?: substr((string) $shop->first_date, 0, 10);
                $windowTo   = now()->toDateString();

                $rows[] = [
                    'platform'  => $platform->slug,
                    'shop_id'   => $shop->shop_id,
                    'from'      => $windowFrom,
                    'to'        => $windowTo,
                    'invoices'  => $shop->invoice_count,
                ];

                if ($commit) {
                    foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                        ProcessPlatformTimeSeriesRecords::dispatch($platform->id, $shop->shop_id, $frequency, $windowFrom, $windowTo);
                        $jobs++;
                    }
                }
            }
        }

        if ($rows === []) {
            $command->info('No manual platform invoices with a sales channel found.');

            return 0;
        }

        $command->table(['Platform', 'Shop', 'From', 'To', 'Invoices'], $rows);
        $command->info($commit
            ? "Dispatched $jobs jobs to the sales_slave queue."
            : 'Dry run: pass --commit to dispatch the jobs.');

        return 0;
    }
}
