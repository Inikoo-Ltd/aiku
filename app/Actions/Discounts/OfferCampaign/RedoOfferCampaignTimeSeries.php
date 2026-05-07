<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Discounts\OfferCampaign;

use App\Actions\Traits\Hydrators\WithHydrateCommand;
use App\Actions\Traits\WithTimeSeriesRedo;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Discounts\OfferCampaign;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RedoOfferCampaignTimeSeries implements ShouldBeUnique
{
    use WithHydrateCommand;
    use WithTimeSeriesRedo {
        WithTimeSeriesRedo::asCommand insteadof WithHydrateCommand;
    }

    public string $jobQueue = 'default-long-slave';
    public string $commandSignature = 'offer-campaigns:redo_time_series {--from= : Start date (Y-m-d)} {--to= : End date (Y-m-d)} {--a|async : Run asynchronously}';

    public function __construct()
    {
        $this->model = OfferCampaign::class;
    }

    public function getJobUniqueId(?int $offerCampaignId, string $from, string $to): string
    {
        if ($offerCampaignId === null) {
            return 'empty'.'_'.$from.'_'.$to;
        }

        return $offerCampaignId.'_'.$from.'_'.$to;
    }

    public function handle(?int $offerCampaignId, ?string $from = null, ?string $to = null, bool $async = false): void
    {
        if (!$offerCampaignId) {
            return;
        }

        $offerCampaign = OfferCampaign::find($offerCampaignId);

        if (!$offerCampaign) {
            return;
        }

        if (!$offerCampaign->status) {
            return;
        }

        if (!$from || !$to) {
            $firstTransactionDate = DB::connection('aiku_no_sticky')->table('invoice_transactions')
                ->whereExists(function ($query) use ($offerCampaign) {
                    $query->select(DB::raw(1))
                        ->from('transaction_has_offer_allowances')
                        ->whereColumn('transaction_has_offer_allowances.transaction_id', 'invoice_transactions.transaction_id')
                        ->where('transaction_has_offer_allowances.offer_campaign_id', $offerCampaign->id);
                })
                ->whereNull('deleted_at')
                ->min('date');

            $lastTransactionDate = DB::connection('aiku_no_sticky')->table('invoice_transactions')
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

            $from = $from ?? Carbon::parse($firstTransactionDate)->toDateString();
            $to   = $to ?? Carbon::parse($lastTransactionDate ?? now())->toDateString();
        }

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            if ($async) {
                ProcessOfferCampaignTimeSeriesRecords::dispatch($offerCampaign->id, $frequency, $from, $to);
            } else {
                ProcessOfferCampaignTimeSeriesRecords::run($offerCampaign->id, $frequency, $from, $to);
            }
        }
    }
}
