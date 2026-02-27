<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 Jan 2026 02:52:11 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterCollectionTimeSeries;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Masters\MasterCollection;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class RedoMasterCollectionTimeSeries implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'default-long';
    public string $commandSignature = 'master_collections:redo_time_series {--a|async : Run asynchronously}';

    public function getJobUniqueId(string $from, string $to): string
    {
        return "{$from}_{$to}";
    }

    public function handle(MasterCollection $masterCollection, bool $async = false): void
    {
        $masterAssetsIDs = $masterCollection->masterProducts()->pluck('master_assets.id')->unique()->toArray();

        $firstInvoicedDate = DB::table('invoice_transactions')->whereIn('master_asset_id', $masterAssetsIDs)->whereNull('deleted_at')->min('date');
        $lastInvoicedDate  = DB::table('invoice_transactions')->whereIn('master_asset_id', $masterAssetsIDs)->whereNull('deleted_at')->max('date');

        if (!$firstInvoicedDate) {
            return;
        }

        $from = Carbon::parse($firstInvoicedDate)->toDateString();
        $to   = Carbon::parse($lastInvoicedDate ?? now())->toDateString();

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            if ($async) {
                ProcessMasterCollectionTimeSeriesRecords::dispatch($masterCollection->id, $frequency, $from, $to)->onQueue('low-priority');
            } else {
                ProcessMasterCollectionTimeSeriesRecords::run($masterCollection->id, $frequency, $from, $to);
            }
        }
    }

    public function asJob(string $from, string $to): void
    {
        MasterCollection::all()->each(function (MasterCollection $masterCollection) use ($from, $to) {
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                ProcessMasterCollectionTimeSeriesRecords::run($masterCollection->id, $frequency, $from, $to);
            }
        });
    }

    public function asCommand(Command $command): int
    {
        $command->info($command->getName());

        $async = (bool) $command->option('async');

        $masterCollections = MasterCollection::all();

        $bar = $command->getOutput()->createProgressBar($masterCollections->count());
        $bar->setFormat('debug');
        $bar->start();

        foreach ($masterCollections as $masterCollection) {
            try {
                $this->handle($masterCollection, $async);
            } catch (Throwable $e) {
                $command->error($e->getMessage());
            }
            $bar->advance();
        }

        $bar->finish();
        $command->info('');

        return 0;
    }
}
