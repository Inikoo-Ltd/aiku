<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 14 Feb 2025 12:51:07 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice\UI;

use App\Actions\Accounting\Invoice\DestroyRefund;
use App\Actions\OrgAction;
use App\Models\Accounting\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class DeleteRefund extends OrgAction
{
    public function handle(Invoice $invoice): Invoice
    {

        if (!$invoice->in_process) {
            return $invoice;
        }
        DestroyRefund::make()->action($invoice, []);
        return $invoice;
    }

    public function htmlResponse(Invoice $refund, ActionRequest $request): RedirectResponse
    {
        return Redirect::route('grp.org.fulfilments.show.crm.customers.show.invoices.index', [
            $refund->organisation->slug,
            $refund->customer->fulfilmentCustomer->fulfilment->slug,
            $refund->customer->fulfilmentCustomer->slug,
        ]);
    }

    public function asController(Invoice $refund, ActionRequest $request): Invoice
    {
        $this->initialisationFromShop($refund->shop, $request);

        return $this->handle($refund);
    }
}
