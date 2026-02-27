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
use App\Enums\DateIntervals\DateIntervalEnum;
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

            RedoProductTimeSeries::dispatch($invoiceDate->toDateString(), $invoiceDate->toDateString())->delay($this->hydratorsDelay);
            RedoCollectionTimeSeries::dispatch($invoiceDate->toDateString(), $invoiceDate->toDateString())->delay($this->hydratorsDelay);
        }

        if ($invoiceTransaction->family_id) {
            RedoFamiliesTimeSeries::dispatch($invoiceDate->toDateString(), $invoiceDate->toDateString())->delay($this->hydratorsDelay);
        }

        if ($invoiceTransaction->department_id) {
            RedoDepartmentsTimeSeries::dispatch($invoiceDate->toDateString(), $invoiceDate->toDateString())->delay($this->hydratorsDelay);
        }

        if ($invoiceTransaction->sub_department_id) {
            RedoSubDepartmentsTimeSeries::dispatch($invoiceDate->toDateString(), $invoiceDate->toDateString())->delay($this->hydratorsDelay);
        }

        if ($invoiceTransaction->master_asset_id) {
            RedoMasterAssetTimeSeries::dispatch($invoiceDate->toDateString(), $invoiceDate->toDateString())->delay($this->hydratorsDelay);
            RedoMasterCollectionTimeSeries::dispatch($invoiceDate->toDateString(), $invoiceDate->toDateString())->delay($this->hydratorsDelay);
        }

        if ($invoiceTransaction->master_family_id) {
            RedoMasterFamiliesTimeSeries::dispatch($invoiceDate->toDateString(), $invoiceDate->toDateString())->delay($this->hydratorsDelay);
        }

        if ($invoiceTransaction->master_department_id) {
            RedoMasterDepartmentsTimeSeries::dispatch($invoiceDate->toDateString(), $invoiceDate->toDateString())->delay($this->hydratorsDelay);
        }

        if ($invoiceTransaction->master_sub_department_id) {
            RedoMasterSubDepartmentsTimeSeries::dispatch($invoiceDate->toDateString(), $invoiceDate->toDateString())->delay($this->hydratorsDelay);
        }

        if ($invoiceTransaction->transaction) {
            if ($invoiceTransaction->offerAllowances->isNotEmpty()) {
                RedoOfferTimeSeries::dispatch($invoiceDate->toDateString(), $invoiceDate->toDateString())->delay($this->hydratorsDelay);
                RedoOfferCampaignTimeSeries::dispatch($invoiceDate->toDateString(), $invoiceDate->toDateString())->delay($this->hydratorsDelay);
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
