<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Tue, 04 Mar 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Accounting\InvoiceTransaction;

use App\Actions\Catalogue\AssetTimeSeries\ProcessAssetTimeSeriesRecords;
use App\Actions\Catalogue\CollectionTimeSeries\PreprocessCollectionTimeSeries;
use App\Actions\Catalogue\ProductCategoryTimeSeries\ProcessProductCategoryTimeSeriesRecords;
use App\Actions\Discounts\Offer\ProcessOfferTimeSeriesRecords;
use App\Actions\Discounts\OfferCampaign\ProcessOfferCampaignTimeSeriesRecords;
use App\Actions\Masters\MasterAssetTimeSeries\ProcessMasterAssetTimeSeriesRecords;
use App\Actions\Masters\MasterCollectionTimeSeries\PreprocessMasterCollectionTimeSeries;
use App\Actions\Masters\MasterProductCategoryTimeSeries\ProcessMasterProductCategoryTimeSeriesRecords;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Accounting\InvoiceTransaction;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessInvoiceTransactionTimeSeries implements ShouldQueue
{
    use AsAction;

    public function handle(
        InvoiceTransaction $invoiceTransaction,
        ?string $date = null,
        bool $withAsset = true,
        bool $withProductCategories = true,
        bool $withMasterAsset = true,
        bool $withMasterProductCategories = true,
        bool $withOffers = true,
        int $delay = 1800,
    ): void {
        $resolvedDate = $date
            ? Carbon::parse($date)->toDateString()
            : Carbon::parse($invoiceTransaction->date)->toDateString();

        $periodDates = $this->resolvePeriodDates($resolvedDate);

        if ($withAsset && $invoiceTransaction->asset_id) {
            PreprocessCollectionTimeSeries::dispatch($invoiceTransaction->asset_id, $resolvedDate)->delay(30);

            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                ProcessAssetTimeSeriesRecords::dispatch(
                    $invoiceTransaction->asset_id,
                    $frequency,
                    $periodDates[$frequency->value]['from'],
                    $resolvedDate
                )->delay($delay);
            }
        }

        if ($withProductCategories) {
            foreach ([
                'family_id'         => $invoiceTransaction->family_id,
                'department_id'     => $invoiceTransaction->department_id,
                'sub_department_id' => $invoiceTransaction->sub_department_id,
            ] as $categoryId) {
                if (!$categoryId) {
                    continue;
                }

                foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                    ProcessProductCategoryTimeSeriesRecords::dispatch(
                        $categoryId,
                        $frequency,
                        $periodDates[$frequency->value]['from'],
                        $resolvedDate
                    )->delay($delay);
                }
            }
        }

        if ($withMasterAsset && $invoiceTransaction->master_asset_id) {
            PreprocessMasterCollectionTimeSeries::dispatch($invoiceTransaction->master_asset_id, $resolvedDate)->delay(30);

            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                ProcessMasterAssetTimeSeriesRecords::dispatch(
                    $invoiceTransaction->master_asset_id,
                    $frequency,
                    $periodDates[$frequency->value]['from'],
                    $resolvedDate
                )->delay($delay);
            }
        }

        if ($withMasterProductCategories) {
            foreach ([
                'master_family_id'         => $invoiceTransaction->master_family_id,
                'master_department_id'     => $invoiceTransaction->master_department_id,
                'master_sub_department_id' => $invoiceTransaction->master_sub_department_id,
            ] as $masterCategoryId) {
                if (!$masterCategoryId) {
                    continue;
                }

                foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                    ProcessMasterProductCategoryTimeSeriesRecords::dispatch(
                        $masterCategoryId,
                        $frequency,
                        $periodDates[$frequency->value]['from'],
                        $resolvedDate
                    )->delay($delay);
                }
            }
        }

        if ($withOffers && $invoiceTransaction->transaction) {
            foreach ($invoiceTransaction->transaction->offerAllowances as $offerAllowance) {
                foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                    ProcessOfferTimeSeriesRecords::dispatch(
                        $offerAllowance->offer_id,
                        $frequency,
                        $periodDates[$frequency->value]['from'],
                        $resolvedDate
                    )->delay($delay);

                    ProcessOfferCampaignTimeSeriesRecords::dispatch(
                        $offerAllowance->offer_campaign_id,
                        $frequency,
                        $periodDates[$frequency->value]['from'],
                        $resolvedDate
                    )->delay($delay);
                }
            }
        }
    }

    protected function resolvePeriodDates(string $date): array
    {
        $carbon = Carbon::parse($date);

        $dates = [];

        foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
            $dates[$frequency->value] = [
                'from' => match ($frequency) {
                    TimeSeriesFrequencyEnum::YEARLY    => $carbon->copy()->startOfYear()->toDateString(),
                    TimeSeriesFrequencyEnum::QUARTERLY => $carbon->copy()->startOfQuarter()->toDateString(),
                    TimeSeriesFrequencyEnum::MONTHLY   => $carbon->copy()->startOfMonth()->toDateString(),
                    TimeSeriesFrequencyEnum::WEEKLY    => $carbon->copy()->startOfWeek()->toDateString(),
                    TimeSeriesFrequencyEnum::DAILY     => $date,
                },
                'to'   => $date,
            ];
        }

        return $dates;
    }
}
