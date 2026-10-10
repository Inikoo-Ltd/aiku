<?php

namespace App\Actions\Traits\Authorisations;

use App\Models\Accounting\Invoice;

trait WithInvoiceEditPermissions
{
    /**
     * Changing an invoice or a refund is accounting work. On a fulfilment shop the fulfilment
     * team invoices its own customers, so it keeps that right there.
     */
    protected function getInvoiceEditPermissions(Invoice $invoice): array
    {
        $permissions = ["accounting.$invoice->organisation_id.edit"];

        if ($fulfilmentId = $invoice->shop->fulfilment?->id) {
            $permissions[] = "fulfilment-shop.$fulfilmentId.edit";
        }

        return $permissions;
    }
}
