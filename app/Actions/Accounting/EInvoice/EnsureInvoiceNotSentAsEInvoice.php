<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\EInvoice;

use App\Models\Accounting\Invoice;
use DateTimeInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsObject;

class EnsureInvoiceNotSentAsEInvoice
{
    use AsObject;

    /**
     * Fields printed on the e-invoice. Addresses and tax numbers count as changed whenever they are given.
     */
    public const array CONTENT_FIELDS = [
        'reference', 'date', 'tax_liability_at', 'currency_id', 'net_amount', 'tax_amount', 'total_amount',
        'customer_name', 'fiscal_name', 'tax_number', 'formatted_tax_number', 'identity_document_number',
        'billing_address', 'invoice_billing_address', 'billing_country_id', 'is_re', 'tax_category_id', 'tax_number_valid',
        'original_invoice_id', 'address_id',
    ];

    private const array AMOUNT_FIELDS = ['net_amount', 'tax_amount', 'total_amount'];

    /**
     * Once SOFTIP has accepted an e-invoice it is the legal invoice: it is corrected with a credit note, never edited or deleted.
     * A document SOFTIP refused as INVALID never left, so it can still be fixed and sent again. A send that got no answer
     * (QUEUED after an attempt) may already be at SOFTIP and is retried with the same XML, so it counts as sent.
     * An invoice created in this request is only sent after its transaction commits, and a line without an invoice has
     * nothing sent, so neither is looked up.
     *
     * @param array<string, mixed>|null $modelData null for a delete, otherwise the fields about to change
     *
     * @throws ValidationException
     */
    public function handle(?Invoice $invoice, ?array $modelData = null): void
    {
        if (!$invoice || $invoice->wasRecentlyCreated) {
            return;
        }

        if ($modelData !== null && !$this->changesContent($invoice, $modelData)) {
            return;
        }

        if (self::isSent($invoice)) {
            throw ValidationException::withMessages([
                'e_invoice' => __('This invoice was sent as a Peppol e-invoice, so it can no longer be changed or deleted. Correct it with a credit note.'),
            ]);
        }
    }

    public static function isSent(Invoice $invoice): bool
    {
        return $invoice->eInvoiceTransmissions()
            ->where(fn ($query) => $query
                ->where(fn ($query) => $query->whereNotNull('transaction_id')->where('status', '!=', 'INVALID'))
                ->orWhere(fn ($query) => $query->where('status', 'QUEUED')->whereNotNull('first_attempted_at')))
            ->exists();
    }

    /**
     * @param array<string, mixed> $modelData
     */
    private function changesContent(Invoice $invoice, array $modelData): bool
    {
        foreach (Arr::only($modelData, self::CONTENT_FIELDS) as $field => $value) {
            if (is_array($value) || !array_key_exists($field, $invoice->getAttributes())) {
                return true;
            }
            $current = $invoice->getAttribute($field);
            if ($current instanceof DateTimeInterface) {
                if ($value === null || $current->format('Y-m-d H:i:s') !== Carbon::parse($value)->format('Y-m-d H:i:s')) {
                    return true;
                }
            } elseif (in_array($field, self::AMOUNT_FIELDS, true)) {
                if (abs((float)$current - (float)$value) > 0.000001) {
                    return true;
                }
            } elseif ((string)$current !== (string)$value) {
                return true;
            }
        }

        return false;
    }
}
