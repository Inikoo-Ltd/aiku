<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Discounts\Offer;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Actions\Traits\WithTimeSeriesRedo;
use App\Enums\Discounts\Offer\OfferStateEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Discounts\Offer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RedoOfferTimeSeries implements ShouldBeUnique
{
    use WithHydrateCommand;
    use WithTimeSeriesRedo {
        WithTimeSeriesRedo::asCommand insteadof WithHydrateCommand;
    }

    public string $jobQueue = 'default-long';
    public string $commandSignature = 'offers:redo_time_series {--from= : Start date (Y-m-d)} {--to= : End date (Y-m-d)} {--a|async : Run asynchronously}';

    public function __construct()
    {
        $this->model = Offer::class;
    }

    public function getJobUniqueId(?int $offerID, ?string $from, ?string $to): string
    {
        return $offerID ?? 'empty'.'_'.$from.'_'.$to;
    }

    public function handle(Offer $offer, ?string $from = null, ?string $to = null, bool $async = false): void
    {
        if ($offer->state == OfferStateEnum::IN_PROCESS) {
            return;
        }

        if (!$from || !$to) {
            $firstTransactionDate = DB::table('invoice_transactions')
                ->whereExists(function ($query) use ($offer) {
                    $query->select(DB::raw(1))
                        ->from('transaction_has_offer_allowances')
                        ->whereColumn('transaction_has_offer_allowances.transaction_id', 'invoice_transactions.transaction_id')
                        ->where('transaction_has_offer_allowances.offer_id', $offer->id);
                })
                ->whereNull('deleted_at')
                ->min('date');

            $lastTransactionDate = DB::table('invoice_transactions')
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

            $from = $from ?? Carbon::parse($firstTransactionDate)->toDateString();
            $to   = $to ?? Carbon::parse($lastTransactionDate ?? now())->toDateString();
        }

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            if ($async) {
                ProcessOfferTimeSeriesRecords::dispatch($offer->id, $frequency, $from, $to)->onQueue('low-priority');
            } else {
                ProcessOfferTimeSeriesRecords::run($offer->id, $frequency, $from, $to);
            }
        }
    }

}
