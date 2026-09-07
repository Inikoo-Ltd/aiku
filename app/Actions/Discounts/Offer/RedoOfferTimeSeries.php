<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Discounts\Offer;

use App\Helpers\TimeSeriesPeriodCalculator;
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

    public string $jobQueue = 'long-low-priority';
    public string $commandSignature = 'offers:redo_time_series {--S|shop= : Shop slug} {--O|organisation= : Organisation slug} {--from= : Start date (Y-m-d)} {--to= : End date (Y-m-d)} {--a|async : Run asynchronously}';

    public function __construct()
    {
        $this->model = Offer::class;
    }

    public function getJobUniqueId(?int $offerID, ?string $from, ?string $to): string
    {
        if ($offerID === null) {
            return 'empty'.'_'.$from.'_'.$to;
        }

        return $offerID.'_'.$from.'_'.$to;
    }

    protected function dateRangeSources(): array
    {
        return [
            [
                'query' => fn () => DB::connection('aiku_no_sticky')->table('invoice_transactions')
                    ->join('transaction_has_offer_allowances', 'transaction_has_offer_allowances.transaction_id', '=', 'invoice_transactions.transaction_id')
                    ->whereNull('invoice_transactions.deleted_at'),
                'key'   => 'transaction_has_offer_allowances.offer_id',
                'date'  => 'invoice_transactions.date',
            ],
        ];
    }

    public function handle(?int  $offerId, ?string $from = null, ?string $to = null, bool $async = false): void
    {
        if (!$offerId) {
            return;
        }
        $offer = Offer::find($offerId);
        if (!$offer) {
            return;
        }

        if ($offer->state == OfferStateEnum::IN_PROCESS) {
            return;
        }

        if (!$from || !$to) {
            $dateRange = $this->getDateRange($offer->id);

            if (!$dateRange['from']) {
                return;
            }

            $from = $from ?? Carbon::parse($dateRange['from'])->toDateString();
            $to   = $to ?? Carbon::parse($dateRange['to'] ?? now())->toDateString();
        }

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            [$periodFrom, $periodTo] = TimeSeriesPeriodCalculator::expandWindowToFullPeriods($frequency, $from, $to);

            if ($async) {
                ProcessOfferTimeSeriesRecords::dispatch($offer->id, $frequency, $periodFrom, $periodTo);
            } else {
                ProcessOfferTimeSeriesRecords::run($offer->id, $frequency, $periodFrom, $periodTo);
            }
        }
    }
}
