<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 14 Feb 2025 12:51:07 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\Invoice\UI;

use App\Actions\CRM\Customer\Hydrators\CustomerHydrateInvoices;
use App\Actions\OrgAction;
use App\Enums\UI\Accounting\InvoicesTabsEnum;
use App\Models\Accounting\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class ForceDeleteRefund extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(Invoice $refund): Invoice
    {
        if (!$refund->in_process) {
            return $refund;
        }

        DB::transaction(function () use ($refund) {
            $refund->invoiceTransactions()->forceDelete();
            $refund->forceDelete();
        });
        if ($refund->customer_id) {
            CustomerHydrateInvoices::dispatch($refund->customer);
        }

        return $refund;
    }

    public function htmlResponse(Invoice $refund): RedirectResponse
    {
        return Redirect::route('grp.org.fulfilments.show.crm.customers.show.invoices.index', [
            $refund->organisation->slug,
            $refund->customer->fulfilmentCustomer->fulfilment->slug,
            $refund->customer->fulfilmentCustomer->slug,
            'tab' => InvoicesTabsEnum::REFUNDS->value
        ]);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Invoice $refund, ActionRequest $request): Invoice
    {
        $this->initialisationFromShop($refund->shop, $request);

        return $this->handle($refund);
    }
}
