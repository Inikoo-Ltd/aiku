<?php

namespace App\Actions\Traits\Authorisations;

use Lorisleiva\Actions\ActionRequest;

trait WithInvoiceDownloadAuthorisation
{
    use WithInvoiceAuthorisation {
        authorize as protected authorizeInvoicePage;
    }

    /**
     * The invoice PDF is downloaded from the customer and order pages as well as from accounting,
     * so anyone who may see the invoice somewhere may download it.
     */
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $invoice = $this->getInvoiceToAuthorise($request);

        return $invoice && $request->user()->authTo($this->getInvoiceViewPermissions($invoice));
    }
}
