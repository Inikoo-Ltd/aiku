<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 04 Sept 2024 15:22:41 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
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
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\DateIntervals\DateIntervalEnum;
use App\Enums\Helpers\TimeSeries\TimeSeriesFrequencyEnum;
use App\Models\Accounting\InvoiceTransaction;

class UpdateInvoiceTransaction extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;


    public function handle(InvoiceTransaction $invoiceTransaction, array $modelData): InvoiceTransaction
    {
        $invoiceTransaction = $this->update($invoiceTransaction, $modelData, ['data']);

        $invoiceDate = \Carbon\Carbon::parse($invoiceTransaction->date);
        $intervalsExceptHistorical = DateIntervalEnum::allExceptHistorical();

        if ($invoiceTransaction->asset_id) {
            AssetHydrateSalesIntervals::dispatch($invoiceTransaction->asset_id, $intervalsExceptHistorical, [])->delay($this->hydratorsDelay);
            AssetHydrateInvoiceIntervals::dispatch($invoiceTransaction->asset_id, $intervalsExceptHistorical, [])->delay($this->hydratorsDelay);
            AssetHydrateInvoicedCustomersIntervals::dispatch($invoiceTransaction->asset_id, $intervalsExceptHistorical, [])->delay($this->hydratorsDelay);
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

    public function rules(): array
    {
        $rules = [
            'quantity'            => ['sometimes', 'required', 'numeric', 'min:0'],
            'quantity_ordered'    => ['sometimes', 'required', 'numeric', 'min:0'],
            'quantity_bonus'      => ['sometimes', 'required', 'numeric', 'min:0'],
            'quantity_dispatched' => ['sometimes', 'required', 'numeric', 'min:0'],
            'quantity_fail'       => ['sometimes', 'required', 'numeric', 'min:0'],
            'quantity_cancelled'  => ['sometimes', 'sometimes', 'numeric', 'min:0'],
            'gross_amount'        => ['sometimes', 'required', 'numeric'],
            'net_amount'          => ['sometimes', 'required', 'numeric'],
            'org_exchange'        => ['sometimes', 'numeric'],
            'grp_exchange'        => ['sometimes', 'numeric'],
            'org_net_amount'      => ['sometimes', 'numeric'],
            'grp_net_amount'      => ['sometimes', 'numeric'],
            'tax_category_id'     => ['sometimes', 'required', 'exists:tax_categories,id'],
            'date'                => ['sometimes', 'required', 'date'],
            'submitted_at'        => ['sometimes', 'required', 'date'],
        ];
        if (!$this->strict) {
            $rules['model_type']        = ['sometimes', 'required', 'string'];
            $rules['model_id']          = ['sometimes', 'nullable', 'integer'];
            $rules['asset_id']          = ['sometimes', 'nullable', 'integer'];
            $rules['historic_asset_id'] = ['sometimes', 'nullable', 'integer'];
            $rules['order_id']          = ['sometimes', 'nullable', 'integer'];
            $rules['transaction_id']    = ['sometimes', 'nullable', 'integer'];

            $rules = $this->noStrictUpdateRules($rules);
        }

        return $rules;
    }

    public function action(InvoiceTransaction $invoiceTransaction, array $modelData, int $hydratorsDelay = 1800, bool $strict = true): InvoiceTransaction
    {
        $this->strict = $strict;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($invoiceTransaction->shop, $modelData);

        return $this->handle($invoiceTransaction, $this->validatedData);
    }
}
