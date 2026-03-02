<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Masters\MasterShop;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Masters\MasterShop;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class RedoMasterShopTimeSeries implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'default-long';
    public string $commandSignature = 'master-shops:redo_time_series {--a|async : Run asynchronously}';

    public function getJobUniqueId(string $from, string $to): string
    {
        return "{$from}_{$to}";
    }

    public function handle(MasterShop $masterShop, bool $async = false): void
    {
        $dates = collect([
            DB::table('invoices')->where('master_shop_id', $masterShop->id)->whereNull('deleted_at')->selectRaw('MIN(date) as min_date, MAX(date) as max_date')->first(),
            DB::table('orders')->where('master_shop_id', $masterShop->id)->whereNull('deleted_at')->selectRaw('MIN(created_at) as min_date, MAX(created_at) as max_date')->first(),
            DB::table('delivery_notes')
                ->join('delivery_note_order', 'delivery_notes.id', '=', 'delivery_note_order.delivery_note_id')
                ->join('orders', 'delivery_note_order.order_id', '=', 'orders.id')
                ->where('orders.master_shop_id', $masterShop->id)
                ->whereNull('delivery_notes.deleted_at')
                ->selectRaw('MIN(delivery_notes.date) as min_date, MAX(delivery_notes.date) as max_date')
                ->first(),
            DB::table('customers')->where('master_shop_id', $masterShop->id)->whereNull('deleted_at')->selectRaw('MIN(registered_at) as min_date, MAX(registered_at) as max_date')->first(),
        ]);

        $firstActivityDate = $dates->pluck('min_date')->filter()->min();
        $lastActivityDate  = $dates->pluck('max_date')->filter()->max();

        if (!$firstActivityDate) {
            return;
        }

        $from = Carbon::parse($firstActivityDate)->toDateString();
        $to   = Carbon::parse($lastActivityDate ?? now())->toDateString();

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            if ($async) {
                ProcessMasterShopTimeSeriesRecords::dispatch($masterShop->id, $frequency, $from, $to)->onQueue('low-priority');
            } else {
                ProcessMasterShopTimeSeriesRecords::run($masterShop->id, $frequency, $from, $to);
            }
        }
    }

    public function asJob(string $from, string $to): void
    {
        MasterShop::all()->each(function (MasterShop $masterShop) use ($from, $to) {
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                ProcessMasterShopTimeSeriesRecords::run($masterShop->id, $frequency, $from, $to);
            }
        });
    }

    public function asCommand(Command $command): int
    {
        $command->info($command->getName());

        $async = (bool) $command->option('async');

        $masterShops = MasterShop::all();

        $bar = $command->getOutput()->createProgressBar($masterShops->count());
        $bar->setFormat('debug');
        $bar->start();

        foreach ($masterShops as $masterShop) {
            try {
                $this->handle($masterShop, $async);
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
