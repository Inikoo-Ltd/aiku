<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\EInvoice;

use App\Actions\Retina\SysAdmin\UpdateRetinaCustomer;
use App\Models\Accounting\Invoice;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Carbon;
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
        $invoice->loadMissing(['organisation', 'address', 'customer', 'shop.taxNumber']);

        $countries = Arr::get($invoice->organisation->settings, 'e_invoicing.countries', []);
        if (!$countries || $invoice->in_process || !in_array($invoice->address?->country_code, $countries, true)) {
            return false;
        }

        if ($invoice->date && !self::hasStarted($invoice->organisation, $invoice->date)) {
            return false;
        }

        $buyer = BuildUblInvoice::buyerParticipantId($invoice);
        if ($buyer !== null && $buyer === self::ownParticipantId($invoice)) {
            return false;
        }

        if ($buyer === null && $invoice->customer && UpdateRetinaCustomer::buysAsPrivatePerson($invoice->customer)) {
            return false;
        }

        return $buyer !== null
            || strlen(preg_replace('/\D/', '', (string)$invoice->tax_number)) >= 8
            || strlen(preg_replace('/\D/', '', (string)$invoice->identity_document_number)) >= 8
            || trim((string)$invoice->customer?->company_name) !== '';
    }

    /**
     * Our own Peppol ID (from the shop's VAT number): staff and own-company accounts carrying it are never e-invoiced to ourselves.
     *
     * @return array{scheme: string, id: string}|null
     */
    private static function ownParticipantId(Invoice $invoice): ?array
    {
        $taxNumber = $invoice->shop?->taxNumber;

        return $taxNumber ? BuildUblInvoice::participantId($taxNumber->country_code.$taxNumber->number) : null;
    }

    /**
     * E-invoicing starts on the organisation's start date (settings e_invoicing.starts_on, in its own timezone); none set: already started.
     */
    public static function hasStarted(Organisation $organisation, ?Carbon $at = null): bool
    {
        $startsOn = Arr::get($organisation->settings, 'e_invoicing.starts_on');
        if (!$startsOn) {
            return true;
        }

        return ($at ?? now())->copy()->setTimezone($organisation->timezone?->name ?? 'UTC')->toDateString() >= $startsOn;
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
