<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Discounts\Offer;

use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Discounts\Offer;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class RedoOfferTimeSeries implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'default-long';
    public string $commandSignature = 'offers:redo_time_series {--a|async : Run asynchronously}';

    public function getJobUniqueId(string $from, string $to): string
    {
        return "{$from}_{$to}";
    }

    public function handle(Offer $offer, bool $async = false): void
    {
        if ($offer->state == OfferStateEnum::IN_PROCESS) {
            return;
        }

        $firstTransactionDate = DB::table('invoice_transactions')
            ->whereExists(function ($query) use ($offer) {
                $query->select(DB::raw(1))
                      ->from('transaction_has_offer_allowances')
                      ->whereColumn('transaction_has_offer_allowances.transaction_id', 'invoice_transactions.transaction_id')
                      ->where('transaction_has_offer_allowances.offer_id', $offer->id);
            })
            ->whereNull('deleted_at')
            ->min('date');

        $lastTransactionDate  = DB::table('invoice_transactions')
            ->whereExists(function ($query) use ($offer) {
                $query->select(DB::raw(1))
                      ->from('transaction_has_offer_allowances')
                      ->whereColumn('transaction_has_offer_allowances.transaction_id', 'invoice_transactions.transaction_id')
                      ->where('transaction_has_offer_allowances.offer_id', $offer->id);
            })
            ->whereNull('deleted_at')
            ->max('date');

        if (!$firstTransactionDate) {
            return;
        }

        $from = Carbon::parse($firstTransactionDate)->toDateString();
        $to   = Carbon::parse($lastTransactionDate ?? now())->toDateString();

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            if ($async) {
                ProcessOfferTimeSeriesRecords::dispatch($offer->id, $frequency, $from, $to)->onQueue('low-priority');
            } else {
                ProcessOfferTimeSeriesRecords::run($offer->id, $frequency, $from, $to);
            }
        }
    }

    public function asJob(string $from, string $to): void
    {
        Offer::all()->each(function (Offer $offer) use ($from, $to) {
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                ProcessOfferTimeSeriesRecords::run($offer->id, $frequency, $from, $to);
            }
        });
    }

    public function asCommand(Command $command): int
    {
        $command->info($command->getName());

        $async = (bool) $command->option('async');

        $offers = Offer::all();

        $bar = $command->getOutput()->createProgressBar($offers->count());
        $bar->setFormat('debug');
        $bar->start();

        foreach ($offers as $offer) {
            try {
                $this->handle($offer, $async);
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
