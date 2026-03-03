<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\Product;

use App\Actions\Catalogue\AssetTimeSeries\ProcessAssetTimeSeriesRecords;
use App\Enums\Catalogue\Product\ProductStateEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Catalogue\Product;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class RedoProductTimeSeries implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'default-long';
    public string $commandSignature = 'products:redo_time_series {--a|async : Run asynchronously}';

    public function getJobUniqueId(string $from, string $to): string
    {
        return "{$from}_{$to}";
    }

    public function handle(Product $product, bool $async = false): void
    {
        return; // this fail after 7200 seconds timeout

        if ($product->state == ProductStateEnum::IN_PROCESS) {
            return;
        }

        $firstInvoicedDate = DB::table('invoice_transactions')->where('asset_id', $product->asset_id)->whereNull('deleted_at')->min('date');
        $lastInvoicedDate  = DB::table('invoice_transactions')->where('asset_id', $product->asset_id)->whereNull('deleted_at')->max('date');

        if (!$firstInvoicedDate) {
            return;
        }

        $from = Carbon::parse($firstInvoicedDate)->toDateString();
        $to   = Carbon::parse($lastInvoicedDate ?? now())->toDateString();

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            if ($async) {
                ProcessAssetTimeSeriesRecords::dispatch($product->asset_id, $frequency, $from, $to)->onQueue('low-priority');
            } else {
                ProcessAssetTimeSeriesRecords::run($product->asset_id, $frequency, $from, $to);
            }
        }
    }

    public function asJob(string $from, string $to): void
    {
        Product::all()->each(function (Product $product) use ($from, $to) {
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                ProcessAssetTimeSeriesRecords::run($product->asset_id, $frequency, $from, $to);
            }
        });
    }

    public function asCommand(Command $command): int
    {
        $command->info($command->getName());

        $async = (bool) $command->option('async');

        $products = Product::all();

        $bar = $command->getOutput()->createProgressBar($products->count());
        $bar->setFormat('debug');
        $bar->start();

        foreach ($products as $product) {
            try {
                $this->handle($product, $async);
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
