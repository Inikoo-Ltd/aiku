<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Discounts\OfferCampaign;

use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Discounts\OfferCampaign;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

class RedoOfferCampaignTimeSeries implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'default-long';
    public string $commandSignature = 'offer-campaigns:redo_time_series {--a|async : Run asynchronously}';

    public function getJobUniqueId(string $from, string $to): string
    {
        return "{$from}_{$to}";
    }

    public function handle(OfferCampaign $offerCampaign, bool $async = false): void
    {
        if (!$offerCampaign->status) {
            return;
        }

        $firstTransactionDate = DB::table('invoice_transactions')
            ->whereExists(function ($query) use ($offerCampaign) {
                $query->select(DB::raw(1))
                    ->from('transaction_has_offer_allowances')
                    ->whereColumn('transaction_has_offer_allowances.transaction_id', 'invoice_transactions.transaction_id')
                    ->where('transaction_has_offer_allowances.offer_campaign_id', $offerCampaign->id);
            })
            ->whereNull('deleted_at')
            ->min('date');

        $lastTransactionDate  = DB::table('invoice_transactions')
            ->whereExists(function ($query) use ($offerCampaign) {
                $query->select(DB::raw(1))
                    ->from('transaction_has_offer_allowances')
                    ->whereColumn('transaction_has_offer_allowances.transaction_id', 'invoice_transactions.transaction_id')
                    ->where('transaction_has_offer_allowances.offer_campaign_id', $offerCampaign->id);
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
                ProcessOfferCampaignTimeSeriesRecords::dispatch($offerCampaign->id, $frequency, $from, $to)->onQueue('low-priority');
            } else {
                ProcessOfferCampaignTimeSeriesRecords::run($offerCampaign->id, $frequency, $from, $to);
            }
        }
    }

    public function asJob(string $from, string $to): void
    {
        OfferCampaign::all()->each(function (OfferCampaign $offerCampaign) use ($from, $to) {
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                ProcessOfferCampaignTimeSeriesRecords::run($offerCampaign->id, $frequency, $from, $to);
            }
        });
    }

    public function asCommand(Command $command): int
    {
        $command->info($command->getName());

        $async = (bool) $command->option('async');

        $offerCampaigns = OfferCampaign::all();

        $bar = $command->getOutput()->createProgressBar($offerCampaigns->count());
        $bar->setFormat('debug');
        $bar->start();

        foreach ($offerCampaigns as $offerCampaign) {
            try {
                $this->handle($offerCampaign, $async);
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
