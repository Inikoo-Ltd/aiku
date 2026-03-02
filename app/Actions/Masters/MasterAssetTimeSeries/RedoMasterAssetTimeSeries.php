<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 Jan 2026 00:54:14 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterAssetTimeSeries;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Masters\MasterAsset;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class RedoMasterAssetTimeSeries implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'default-long';
    public string $commandSignature = 'master-assets:redo_time_series {--a|async : Run asynchronously}';

    public function getJobUniqueId(string $from, string $to): string
    {
        return "{$from}_{$to}";
    }

    public function handle(MasterAsset $masterAsset, bool $async = false): void
    {
        $firstInvoicedDate = DB::table('invoice_transactions')->where('master_asset_id', $masterAsset->id)->whereNull('deleted_at')->min('date');
        $lastInvoicedDate  = DB::table('invoice_transactions')->where('master_asset_id', $masterAsset->id)->whereNull('deleted_at')->max('date');

        if (!$firstInvoicedDate) {
            return;
        }

        $from = Carbon::parse($firstInvoicedDate)->toDateString();
        $to   = Carbon::parse($lastInvoicedDate ?? now())->toDateString();

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            if ($async) {
                ProcessMasterAssetTimeSeriesRecords::dispatch($masterAsset->id, $frequency, $from, $to)->onQueue('low-priority');
            } else {
                ProcessMasterAssetTimeSeriesRecords::run($masterAsset->id, $frequency, $from, $to);
            }
        }
    }

    public function asJob(string $from, string $to): void
    {
        MasterAsset::all()->each(function (MasterAsset $masterAsset) use ($from, $to) {
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                ProcessMasterAssetTimeSeriesRecords::run($masterAsset->id, $frequency, $from, $to);
            }
        });
    }

    public function asCommand(Command $command): int
    {
        $command->info($command->getName());

        $async = (bool) $command->option('async');

        $masterAssets = MasterAsset::all();

        $bar = $command->getOutput()->createProgressBar($masterAssets->count());
        $bar->setFormat('debug');
        $bar->start();

        foreach ($masterAssets as $masterAsset) {
            try {
                $this->handle($masterAsset, $async);
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
