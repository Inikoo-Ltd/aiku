<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 10 Apr 2025 22:29:04 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\InvoiceTransaction;

use App\Actions\Catalogue\Asset\Hydrators\AssetHydrateInvoicedCustomersIntervals;
use App\Actions\Catalogue\Asset\Hydrators\AssetHydrateInvoiceIntervals;
use App\Actions\Catalogue\Asset\Hydrators\AssetHydrateSalesIntervals;
use App\Actions\OrgAction;
use App\Enums\DateIntervals\DateIntervalEnum;
use App\Models\Accounting\InvoiceTransaction;

class DeleteInvoiceTransaction extends OrgAction
{
    public function handle(InvoiceTransaction $invoiceTransaction): InvoiceTransaction
    {
        $invoiceTransaction->delete();

        $intervals = DateIntervalEnum::allExceptHistorical();

        AssetHydrateSalesIntervals::dispatch($invoiceTransaction->asset_id, $intervals)->delay($this->hydratorsDelay);
        AssetHydrateInvoiceIntervals::dispatch($invoiceTransaction->asset_id, $intervals)->delay($this->hydratorsDelay);
        AssetHydrateInvoicedCustomersIntervals::dispatch($invoiceTransaction->asset_id, $intervals)->delay($this->hydratorsDelay);


        return $invoiceTransaction;
    }

    public function action(InvoiceTransaction $invoiceTransaction, int $hydratorsDelay = 10): InvoiceTransaction
    {
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($invoiceTransaction->shop, []);

        return $this->handle($invoiceTransaction);
    }

}
