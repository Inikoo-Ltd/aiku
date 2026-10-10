<?php

namespace App\Actions\Traits\Authorisations;

use Lorisleiva\Actions\ActionRequest;

trait WithRefundEditAuthorisation
{
    use WithInvoiceEditAuthorisation {
        authorize as authorizeInvoiceEdit;
    }

    /**
     * Customer service refunds its own customers day to day, the same people who already refund
     * payments, so building and finalising a refund takes CRM edit as well as accounting edit.
     */
    public function authorize(ActionRequest $request): bool
    {
        if ($this->authorizeInvoiceEdit($request)) {
            return true;
        }

        $invoice = $this->getInvoiceToEdit($request);

        return $invoice && $request->user()->authTo("crm.$invoice->shop_id.edit");
    }
}
