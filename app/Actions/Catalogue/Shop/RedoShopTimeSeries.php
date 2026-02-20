<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Catalogue\Shop;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class RedoShopTimeSeries implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'default-long';
    public string $commandSignature = 'shops:redo_time_series {--a|async : Run asynchronously}';

    public function getJobUniqueId(string $from, string $to): string
    {
        return "{$from}_{$to}";
    }

    public function handle(Shop $shop, bool $async = false): void
    {
        $firstActivityDate = collect([
            DB::table('invoices')->where('shop_id', $shop->id)->whereNull('deleted_at')->min('date'),
            DB::table('orders')->where('shop_id', $shop->id)->whereNull('deleted_at')->min('created_at'),
            DB::table('delivery_notes')->where('shop_id', $shop->id)->whereNull('deleted_at')->min('date'),
            DB::table('customers')->where('shop_id', $shop->id)->whereNull('deleted_at')->min('registered_at'),
        ])->filter()->min();

        $lastActivityDate = collect([
            DB::table('invoices')->where('shop_id', $shop->id)->whereNull('deleted_at')->min('date'),
            DB::table('orders')->where('shop_id', $shop->id)->whereNull('deleted_at')->min('created_at'),
            DB::table('delivery_notes')->where('shop_id', $shop->id)->whereNull('deleted_at')->min('date'),
            DB::table('customers')->where('shop_id', $shop->id)->whereNull('deleted_at')->min('registered_at'),
        ])->filter()->max();

        if ($firstActivityDate && ($firstActivityDate < $shop->created_at)) {
            $shop->update(['created_at' => $firstActivityDate]);
        }

        $from = $shop->created_at->toDateString();
        $to   = Carbon::parse($lastActivityDate ?? now())->toDateString();

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            if ($async) {
                ProcessShopTimeSeriesRecords::dispatch($shop->id, $frequency, $from, $to)->onQueue('low-priority');
            } else {
                ProcessShopTimeSeriesRecords::run($shop->id, $frequency, $from, $to);
            }
        }
    }

    public function asJob(string $from, string $to): void
    {
        Shop::all()->each(function (Shop $shop) use ($from, $to) {
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                ProcessShopTimeSeriesRecords::run($shop->id, $frequency, $from, $to);
            }
        });
    }

    public function asCommand(Command $command): int
    {
        $command->info($command->getName());

        $async = (bool) $command->option('async');

        $shops = Shop::all();

        $bar = $command->getOutput()->createProgressBar($shops->count());
        $bar->setFormat('debug');
        $bar->start();

        foreach ($shops as $shop) {
            try {
                $this->handle($shop, $async);
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
