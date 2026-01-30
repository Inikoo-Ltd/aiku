<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 10 Apr 2025 22:29:04 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\InvoiceTransaction;

use App\Actions\Catalogue\Asset\Hydrators\AssetHydrateInvoicedCustomersIntervals;
use App\Actions\Catalogue\Asset\Hydrators\AssetHydrateInvoiceIntervals;
use App\Actions\Catalogue\Asset\Hydrators\AssetHydrateInvoicesCustomersStats;
use App\Actions\Catalogue\Asset\Hydrators\AssetHydrateInvoicesStats;
use App\Actions\Catalogue\Asset\Hydrators\AssetHydrateSalesIntervals;
use App\Actions\Catalogue\AssetTimeSeries\ProcessAssetTimeSeriesRecords;
use App\Actions\Catalogue\ProductCategoryTimeSeries\ProcessProductCategoryTimeSeriesRecords;
use App\Actions\Discounts\Offer\ProcessOfferTimeSeriesRecords;
use App\Actions\Discounts\OfferCampaign\ProcessOfferCampaignTimeSeriesRecords;
use App\Actions\Masters\MasterAssetTimeSeries\ProcessMasterAssetTimeSeriesRecords;
use App\Actions\Masters\MasterProductCategoryTimeSeries\ProcessMasterProductCategoryTimeSeriesRecords;
use App\Actions\OrgAction;
use App\Enums\DateIntervals\DateIntervalEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Accounting\InvoiceTransaction;

class DeleteInvoiceTransaction extends OrgAction
{
    public function handle(InvoiceTransaction $invoiceTransaction): InvoiceTransaction
    {
        $invoiceDate = \Carbon\Carbon::parse($invoiceTransaction->date);
        $intervals = DateIntervalEnum::allExceptHistorical();

        $invoiceTransaction->delete();

        if ($invoiceTransaction->asset_id) {
            AssetHydrateSalesIntervals::dispatch($invoiceTransaction->asset_id, $intervals)->delay($this->hydratorsDelay);
            AssetHydrateInvoiceIntervals::dispatch($invoiceTransaction->asset_id, $intervals)->delay($this->hydratorsDelay);
            AssetHydrateInvoicedCustomersIntervals::dispatch($invoiceTransaction->asset_id, $intervals)->delay($this->hydratorsDelay);
            AssetHydrateInvoicesCustomersStats::dispatch($invoiceTransaction->asset_id)->delay($this->hydratorsDelay);
            AssetHydrateInvoicesStats::dispatch($invoiceTransaction->asset_id)->delay($this->hydratorsDelay);

            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                ProcessAssetTimeSeriesRecords::dispatch(
                    $invoiceTransaction->asset_id,
                    $frequency,
                    match ($frequency) {
                        TimeSeriesFrequencyEnum::YEARLY => $invoiceDate->copy()->startOfYear()->toDateString(),
                        TimeSeriesFrequencyEnum::QUARTERLY => $invoiceDate->copy()->startOfQuarter()->toDateString(),
                        TimeSeriesFrequencyEnum::MONTHLY => $invoiceDate->copy()->startOfMonth()->toDateString(),
                        TimeSeriesFrequencyEnum::WEEKLY => $invoiceDate->copy()->startOfWeek()->toDateString(),
                        TimeSeriesFrequencyEnum::DAILY => $invoiceDate->toDateString()
                    },
                    $invoiceDate->toDateString()
                )->delay($this->hydratorsDelay);
            }
        }

        if ($invoiceTransaction->family_id) {
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                ProcessProductCategoryTimeSeriesRecords::dispatch(
                    $invoiceTransaction->family_id,
                    $frequency,
                    match ($frequency) {
                        TimeSeriesFrequencyEnum::YEARLY => $invoiceDate->copy()->startOfYear()->toDateString(),
                        TimeSeriesFrequencyEnum::QUARTERLY => $invoiceDate->copy()->startOfQuarter()->toDateString(),
                        TimeSeriesFrequencyEnum::MONTHLY => $invoiceDate->copy()->startOfMonth()->toDateString(),
                        TimeSeriesFrequencyEnum::WEEKLY => $invoiceDate->copy()->startOfWeek()->toDateString(),
                        TimeSeriesFrequencyEnum::DAILY => $invoiceDate->toDateString()
                    },
                    $invoiceDate->toDateString()
                )->delay($this->hydratorsDelay);
            }
        }

        if ($invoiceTransaction->department_id) {
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                ProcessProductCategoryTimeSeriesRecords::dispatch(
                    $invoiceTransaction->department_id,
                    $frequency,
                    match ($frequency) {
                        TimeSeriesFrequencyEnum::YEARLY => $invoiceDate->copy()->startOfYear()->toDateString(),
                        TimeSeriesFrequencyEnum::QUARTERLY => $invoiceDate->copy()->startOfQuarter()->toDateString(),
                        TimeSeriesFrequencyEnum::MONTHLY => $invoiceDate->copy()->startOfMonth()->toDateString(),
                        TimeSeriesFrequencyEnum::WEEKLY => $invoiceDate->copy()->startOfWeek()->toDateString(),
                        TimeSeriesFrequencyEnum::DAILY => $invoiceDate->toDateString()
                    },
                    $invoiceDate->toDateString()
                )->delay($this->hydratorsDelay);
            }
        }

        if ($invoiceTransaction->sub_department_id) {
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                ProcessProductCategoryTimeSeriesRecords::dispatch(
                    $invoiceTransaction->sub_department_id,
                    $frequency,
                    match ($frequency) {
                        TimeSeriesFrequencyEnum::YEARLY => $invoiceDate->copy()->startOfYear()->toDateString(),
                        TimeSeriesFrequencyEnum::QUARTERLY => $invoiceDate->copy()->startOfQuarter()->toDateString(),
                        TimeSeriesFrequencyEnum::MONTHLY => $invoiceDate->copy()->startOfMonth()->toDateString(),
                        TimeSeriesFrequencyEnum::WEEKLY => $invoiceDate->copy()->startOfWeek()->toDateString(),
                        TimeSeriesFrequencyEnum::DAILY => $invoiceDate->toDateString()
                    },
                    $invoiceDate->toDateString()
                )->delay($this->hydratorsDelay);
            }
        }

        if ($invoiceTransaction->master_asset_id) {
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                ProcessMasterAssetTimeSeriesRecords::dispatch(
                    $invoiceTransaction->master_asset_id,
                    $frequency,
                    match ($frequency) {
                        TimeSeriesFrequencyEnum::YEARLY => $invoiceDate->copy()->startOfYear()->toDateString(),
                        TimeSeriesFrequencyEnum::QUARTERLY => $invoiceDate->copy()->startOfQuarter()->toDateString(),
                        TimeSeriesFrequencyEnum::MONTHLY => $invoiceDate->copy()->startOfMonth()->toDateString(),
                        TimeSeriesFrequencyEnum::WEEKLY => $invoiceDate->copy()->startOfWeek()->toDateString(),
                        TimeSeriesFrequencyEnum::DAILY => $invoiceDate->toDateString()
                    },
                    $invoiceDate->toDateString()
                )->delay($this->hydratorsDelay);
            }
        }

        if ($invoiceTransaction->master_family_id) {
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                ProcessMasterProductCategoryTimeSeriesRecords::dispatch(
                    $invoiceTransaction->master_family_id,
                    $frequency,
                    match ($frequency) {
                        TimeSeriesFrequencyEnum::YEARLY => $invoiceDate->copy()->startOfYear()->toDateString(),
                        TimeSeriesFrequencyEnum::QUARTERLY => $invoiceDate->copy()->startOfQuarter()->toDateString(),
                        TimeSeriesFrequencyEnum::MONTHLY => $invoiceDate->copy()->startOfMonth()->toDateString(),
                        TimeSeriesFrequencyEnum::WEEKLY => $invoiceDate->copy()->startOfWeek()->toDateString(),
                        TimeSeriesFrequencyEnum::DAILY => $invoiceDate->toDateString()
                    },
                    $invoiceDate->toDateString()
                )->delay($this->hydratorsDelay);
            }
        }

        if ($invoiceTransaction->master_department_id) {
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                ProcessMasterProductCategoryTimeSeriesRecords::dispatch(
                    $invoiceTransaction->master_department_id,
                    $frequency,
                    match ($frequency) {
                        TimeSeriesFrequencyEnum::YEARLY => $invoiceDate->copy()->startOfYear()->toDateString(),
                        TimeSeriesFrequencyEnum::QUARTERLY => $invoiceDate->copy()->startOfQuarter()->toDateString(),
                        TimeSeriesFrequencyEnum::MONTHLY => $invoiceDate->copy()->startOfMonth()->toDateString(),
                        TimeSeriesFrequencyEnum::WEEKLY => $invoiceDate->copy()->startOfWeek()->toDateString(),
                        TimeSeriesFrequencyEnum::DAILY => $invoiceDate->toDateString()
                    },
                    $invoiceDate->toDateString()
                )->delay($this->hydratorsDelay);
            }
        }

        if ($invoiceTransaction->master_sub_department_id) {
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                ProcessMasterProductCategoryTimeSeriesRecords::dispatch(
                    $invoiceTransaction->master_sub_department_id,
                    $frequency,
                    match ($frequency) {
                        TimeSeriesFrequencyEnum::YEARLY => $invoiceDate->copy()->startOfYear()->toDateString(),
                        TimeSeriesFrequencyEnum::QUARTERLY => $invoiceDate->copy()->startOfQuarter()->toDateString(),
                        TimeSeriesFrequencyEnum::MONTHLY => $invoiceDate->copy()->startOfMonth()->toDateString(),
                        TimeSeriesFrequencyEnum::WEEKLY => $invoiceDate->copy()->startOfWeek()->toDateString(),
                        TimeSeriesFrequencyEnum::DAILY => $invoiceDate->toDateString()
                    },
                    $invoiceDate->toDateString()
                )->delay($this->hydratorsDelay);
            }
        }

        foreach ($invoiceTransaction->offerAllowances as $offerAllowance) {
            foreach (TimeSeriesFrequencyEnum::cases() as $frequency) {
                ProcessOfferTimeSeriesRecords::dispatch(
                    $offerAllowance->offer_id,
                    $frequency,
                    match ($frequency) {
                        TimeSeriesFrequencyEnum::YEARLY => $invoiceDate->startOfYear()->toDateString(),
                        TimeSeriesFrequencyEnum::QUARTERLY => $invoiceDate->startOfQuarter()->toDateString(),
                        TimeSeriesFrequencyEnum::MONTHLY => $invoiceDate->startOfMonth()->toDateString(),
                        TimeSeriesFrequencyEnum::WEEKLY => $invoiceDate->startOfWeek()->toDateString(),
                        TimeSeriesFrequencyEnum::DAILY => $invoiceDate->toDateString()
                    },
                    $invoiceDate->toDateString()
                )->delay($this->hydratorsDelay);

                ProcessOfferCampaignTimeSeriesRecords::dispatch(
                    $offerAllowance->offer_campaign_id,
                    $frequency,
                    match ($frequency) {
                        TimeSeriesFrequencyEnum::YEARLY => $invoiceDate->startOfYear()->toDateString(),
                        TimeSeriesFrequencyEnum::QUARTERLY => $invoiceDate->startOfQuarter()->toDateString(),
                        TimeSeriesFrequencyEnum::MONTHLY => $invoiceDate->startOfMonth()->toDateString(),
                        TimeSeriesFrequencyEnum::WEEKLY => $invoiceDate->startOfWeek()->toDateString(),
                        TimeSeriesFrequencyEnum::DAILY => $invoiceDate->toDateString()
                    },
                    $invoiceDate->toDateString()
                )->delay($this->hydratorsDelay);
            }
        }

        return $invoiceTransaction;
    }

    public function action(InvoiceTransaction $invoiceTransaction, int $hydratorsDelay = 1800): InvoiceTransaction
    {
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($invoiceTransaction->shop, []);

        return $this->handle($invoiceTransaction);
    }

}
