<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\CollectionTimeSeries;

use App\Enums\Catalogue\Collection\CollectionStateEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Catalogue\Collection;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class RedoCollectionTimeSeries implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'default-long';
    public string $commandSignature = 'collections:redo_time_series {--a|async : Run asynchronously}';

    public function getJobUniqueId(string $from, string $to): string
    {
        return "{$from}_{$to}";
    }

    public function handle(Collection $collection, bool $async = false): void
    {
        if ($collection->state == CollectionStateEnum::IN_PROCESS || !$collection->source_id) {
            return;
        }

        $assetsIDs = $collection->products->pluck('asset_id')->unique()->toArray();

        $firstInvoicedDate = DB::table('invoice_transactions')->whereIn('asset_id', $assetsIDs)->whereNull('deleted_at')->min('date');
        $lastInvoicedDate  = DB::table('invoice_transactions')->whereIn('asset_id', $assetsIDs)->whereNull('deleted_at')->max('date');

        if (!$firstInvoicedDate) {
            return;
        }

        $from = Carbon::parse($firstInvoicedDate)->toDateString();
        $to   = Carbon::parse($lastInvoicedDate ?? now())->toDateString();

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            if ($async) {
                ProcessCollectionTimeSeriesRecords::dispatch($collection->id, $frequency, $from, $to)->onQueue('low-priority');
            } else {
                ProcessCollectionTimeSeriesRecords::run($collection->id, $frequency, $from, $to);
            }
        }
    }

    public function asJob(string $from, string $to): void
    {
        Collection::all()->each(function (Collection $collection) use ($from, $to) {
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                ProcessCollectionTimeSeriesRecords::run($collection->id, $frequency, $from, $to);
            }
        });
    }

    public function asCommand(Command $command): int
    {
        $command->info($command->getName());

        $async = (bool) $command->option('async');

        $collections = Collection::all();

        $bar = $command->getOutput()->createProgressBar($collections->count());
        $bar->setFormat('debug');
        $bar->start();

        foreach ($collections as $collection) {
            try {
                $this->handle($collection, $async);
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
