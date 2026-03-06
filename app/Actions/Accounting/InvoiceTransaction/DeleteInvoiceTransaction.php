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
use App\Actions\OrgAction;
use App\Enums\DateIntervals\DateIntervalEnum;
use App\Models\Accounting\InvoiceTransaction;
use App\Models\Accounting\InvoiceTransactionHasOrgStock;
use App\Models\Accounting\InvoiceTransactionHasTradeUnit;

class DeleteInvoiceTransaction extends OrgAction
{
    public function handle(InvoiceTransaction $invoiceTransaction): InvoiceTransaction
    {
        $intervals   = DateIntervalEnum::allExceptHistorical();

        InvoiceTransactionHasTradeUnit::where('invoice_transaction_id', $invoiceTransaction->id)->delete();
        InvoiceTransactionHasOrgStock::where('invoice_transaction_id', $invoiceTransaction->id)->delete();

        $invoiceTransaction->delete();

        if ($invoiceTransaction->asset_id) {
            AssetHydrateSalesIntervals::dispatch($invoiceTransaction->asset_id, $intervals)->delay($this->hydratorsDelay);
            AssetHydrateInvoiceIntervals::dispatch($invoiceTransaction->asset_id, $intervals)->delay($this->hydratorsDelay);
            AssetHydrateInvoicedCustomersIntervals::dispatch($invoiceTransaction->asset_id, $intervals)->delay($this->hydratorsDelay);
            AssetHydrateInvoicesCustomersStats::dispatch($invoiceTransaction->asset_id)->delay($this->hydratorsDelay);
            AssetHydrateInvoicesStats::dispatch($invoiceTransaction->asset_id)->delay($this->hydratorsDelay);
        }

        ProcessInvoiceTransactionTimeSeries::run($invoiceTransaction);

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
