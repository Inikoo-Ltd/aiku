<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 17 Mar 2025 14:55:30 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice;

use App\Actions\Catalogue\Shop\Hydrators\ShopHydrateInvoices;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateInvoices;
use App\Actions\OrgAction;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateInvoices;
use App\Actions\SysAdmin\Organisation\Hydrators\OrganisationHydrateInvoices;
use App\Models\Accounting\Invoice;

class CalculateInvoiceTotalsTaxOnly extends OrgAction
{
    public function handle(Invoice $invoice): Invoice
    {
        $transactions = $invoice->invoiceTransactions;
        $taxAmount   = $transactions->sum('tax_amount');
        $totalAmount   = $transactions->sum('amount_total');

        data_set($modelData, 'tax_amount', $taxAmount);
        data_set($modelData, 'total_amount', $totalAmount);

        $invoice->update($modelData);

        GroupHydrateInvoices::dispatch($invoice->group)->delay($this->hydratorsDelay);
        OrganisationHydrateInvoices::dispatch($invoice->organisation)->delay($this->hydratorsDelay);
        CustomerHydrateInvoices::dispatch($invoice->customer_id)->delay($this->hydratorsDelay);
        ShopHydrateInvoices::dispatch($invoice->shop)->delay($this->hydratorsDelay);

        return $invoice;
    }

    public function action(Invoice $invoice, int $hydratorsDelay = 0): Invoice
    {
        $this->asAction       = true;
        $this->hydratorsDelay = $hydratorsDelay;
        $this->initialisationFromShop($invoice->shop, []);

        return $this->handle($invoice);
    }
}
