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
use App\Actions\Catalogue\CollectionTimeSeries\RedoCollectionTimeSeries;
use App\Actions\Catalogue\Product\RedoProductTimeSeries;
use App\Actions\Catalogue\ProductCategory\RedoDepartmentsTimeSeries;
use App\Actions\Catalogue\ProductCategory\RedoFamiliesTimeSeries;
use App\Actions\Catalogue\ProductCategory\RedoSubDepartmentsTimeSeries;
use App\Actions\Discounts\Offer\RedoOfferTimeSeries;
use App\Actions\Discounts\OfferCampaign\RedoOfferCampaignTimeSeries;
use App\Actions\Masters\MasterAssetTimeSeries\RedoMasterAssetTimeSeries;
use App\Actions\Masters\MasterCollectionTimeSeries\RedoMasterCollectionTimeSeries;
use App\Actions\Masters\MasterProductCategoryTimeSeries\RedoMasterDepartmentsTimeSeries;
use App\Actions\Masters\MasterProductCategoryTimeSeries\RedoMasterFamiliesTimeSeries;
use App\Actions\Masters\MasterProductCategoryTimeSeries\RedoMasterSubDepartmentsTimeSeries;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\DateIntervals\DateIntervalEnum;
use App\Models\Accounting\InvoiceTransaction;

class UpdateInvoiceTransaction extends OrgAction
{
    use WithActionUpdate;
    use WithNoStrictRules;


    public function handle(InvoiceTransaction $invoiceTransaction, array $modelData): InvoiceTransaction
    {
        $invoiceTransaction = $this->update($invoiceTransaction, $modelData, ['data']);

        $this->updateTradeUnitBridges($invoiceTransaction);

        $invoiceDate = \Carbon\Carbon::parse($invoiceTransaction->date);
        $intervalsExceptHistorical = DateIntervalEnum::allExceptHistorical();

        if ($invoiceTransaction->asset_id) {
            AssetHydrateSalesIntervals::dispatch($invoiceTransaction->asset_id, $intervalsExceptHistorical, [])->delay($this->hydratorsDelay);
            AssetHydrateInvoiceIntervals::dispatch($invoiceTransaction->asset_id, $intervalsExceptHistorical, [])->delay($this->hydratorsDelay);
            AssetHydrateInvoicedCustomersIntervals::dispatch($invoiceTransaction->asset_id, $intervalsExceptHistorical, [])->delay($this->hydratorsDelay);
            AssetHydrateInvoicesCustomersStats::dispatch($invoiceTransaction->asset_id)->delay($this->hydratorsDelay);
            AssetHydrateInvoicesStats::dispatch($invoiceTransaction->asset_id)->delay($this->hydratorsDelay);

            //RedoProductTimeSeries::dispatch($invoiceDate->toDateString(), $invoiceDate->toDateString())->delay($this->hydratorsDelay);
            //RedoCollectionTimeSeries::dispatch($invoiceDate->toDateString(), $invoiceDate->toDateString())->delay($this->hydratorsDelay);
        }

        if ($invoiceTransaction->family_id) {
            //RedoFamiliesTimeSeries::dispatch($invoiceDate->toDateString(), $invoiceDate->toDateString())->delay($this->hydratorsDelay);
        }

        if ($invoiceTransaction->department_id) {
            //RedoDepartmentsTimeSeries::dispatch($invoiceDate->toDateString(), $invoiceDate->toDateString())->delay($this->hydratorsDelay);
        }

        if ($invoiceTransaction->sub_department_id) {
            //RedoSubDepartmentsTimeSeries::dispatch($invoiceDate->toDateString(), $invoiceDate->toDateString())->delay($this->hydratorsDelay);
        }

        if ($invoiceTransaction->master_asset_id) {
            //RedoMasterAssetTimeSeries::dispatch($invoiceDate->toDateString(), $invoiceDate->toDateString())->delay($this->hydratorsDelay);
            //RedoMasterCollectionTimeSeries::dispatch($invoiceDate->toDateString(), $invoiceDate->toDateString())->delay($this->hydratorsDelay);
        }

        if ($invoiceTransaction->master_family_id) {
            //RedoMasterFamiliesTimeSeries::dispatch($invoiceDate->toDateString(), $invoiceDate->toDateString())->delay($this->hydratorsDelay);
        }

        if ($invoiceTransaction->master_department_id) {
            //RedoMasterDepartmentsTimeSeries::dispatch($invoiceDate->toDateString(), $invoiceDate->toDateString())->delay($this->hydratorsDelay);
        }

        if ($invoiceTransaction->master_sub_department_id) {
            //RedoMasterSubDepartmentsTimeSeries::dispatch($invoiceDate->toDateString(), $invoiceDate->toDateString())->delay($this->hydratorsDelay);
        }

        if ($invoiceTransaction->transaction) {
            if ($invoiceTransaction->offerAllowances->isNotEmpty()) {
                //RedoOfferTimeSeries::dispatch($invoiceDate->toDateString(), $invoiceDate->toDateString())->delay($this->hydratorsDelay);
                //RedoOfferCampaignTimeSeries::dispatch($invoiceDate->toDateString(), $invoiceDate->toDateString())->delay($this->hydratorsDelay);
            }
        }

        return $invoiceTransaction;
    }

    protected function updateTradeUnitBridges(InvoiceTransaction $invoiceTransaction): void
    {
        $invoiceTransaction->tradeUnitBridges()->update([
            'net_amount'      => $invoiceTransaction->net_amount,
            'org_net_amount'  => $invoiceTransaction->org_net_amount,
            'grp_net_amount'  => $invoiceTransaction->grp_net_amount,
            'in_process'      => $invoiceTransaction->in_process ?? false,
            'is_refund'       => $invoiceTransaction->is_refund ?? false,
            'date'            => $invoiceTransaction->date,
            'order_id'        => $invoiceTransaction->order_id,
            'customer_id'     => $invoiceTransaction->customer_id,
        ]);
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
