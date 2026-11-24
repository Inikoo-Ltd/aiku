<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\EInvoice;

use App\Models\Accounting\Invoice;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class IsInvoiceInEInvoiceScope
{
    use AsObject;

    /**
     * A business buyer (Peppol ID, tax or company number, or a company name) in a country the organisation e-invoices.
     * In scope but not addressable on Peppol is still in scope: BuildUblInvoice refuses it so staff can fix the customer.
     */
    public function handle(Invoice $invoice): bool
    {
        $invoice->loadMissing(['organisation', 'address', 'customer']);

        $countries = Arr::get($invoice->organisation->settings, 'e_invoicing.countries', []);
        if (!$countries || $invoice->in_process || !in_array($invoice->address?->country_code, $countries, true)) {
            return false;
        }

        return BuildUblInvoice::buyerParticipantId($invoice) !== null
            || strlen(preg_replace('/\D/', '', (string)$invoice->tax_number)) >= 8
            || strlen(preg_replace('/\D/', '', (string)$invoice->identity_document_number)) >= 8
            || trim((string)$invoice->customer?->company_name) !== '';
    }

    /**
     * Already sent, or in scope and addressable on Peppol, so it will be sent right after it is finalised.
     */
    public function goesAsEInvoice(Invoice $invoice): bool
    {
        return EnsureInvoiceNotSentAsEInvoice::isSent($invoice)
            || ($this->handle($invoice) && BuildUblInvoice::buyerParticipantId($invoice) !== null);
    }
}
