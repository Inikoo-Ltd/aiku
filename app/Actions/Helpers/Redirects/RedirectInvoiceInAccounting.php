<?php

namespace App\Actions\Helpers\Redirects;

use App\Actions\GrpAction;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Models\Accounting\Invoice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class RedirectInvoiceInAccounting extends GrpAction
{
    public function handle(Invoice $invoice): ?RedirectResponse
    {
        $url = route('grp.org.accounting.invoices.show', [
            'organisation'  => $invoice->organisation->slug,
            'invoice'       => $invoice->slug
        ]);

        if ($invoice->type == InvoiceTypeEnum::REFUND) {
            $url = route('grp.org.accounting.refunds.show', [
             'organisation'  => $invoice->organisation->slug,
             'refund'       => $invoice->slug
        ]);
        }


        return Redirect::to($url);
    }

    public function asController(Invoice $invoice, ActionRequest $request): RedirectResponse
    {
        $this->initialisation(group(), $request);

        return $this->handle($invoice);
    }
}
