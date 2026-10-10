<?php

namespace App\Actions\Traits\Authorisations;

use App\Models\Accounting\Invoice;
use App\Models\Accounting\InvoiceTransaction;
use Lorisleiva\Actions\ActionRequest;

trait WithInvoiceEditAuthorisation
{
    use WithInvoiceEditPermissions;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $invoice = $this->getInvoiceToEdit($request);
        if (!$invoice) {
            return false;
        }

        return $request->user()->authTo($this->getInvoiceEditPermissions($invoice));
    }

    protected function getInvoiceToEdit(ActionRequest $request): ?Invoice
    {
        foreach (['refund', 'invoice'] as $parameter) {
            if ($request->route($parameter) instanceof Invoice) {
                return $request->route($parameter);
            }
        }

        $invoiceTransaction = $request->route('invoiceTransaction');

        return $invoiceTransaction instanceof InvoiceTransaction ? $invoiceTransaction->invoice : null;
    }
}
