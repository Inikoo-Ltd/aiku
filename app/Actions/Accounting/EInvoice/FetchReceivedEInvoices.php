<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\EInvoice;

use App\Models\Accounting\ReceivedEInvoice;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * Pulls the documents suppliers sent us over Peppol from the access point. Each one is stored (XML and PDF, unchanged),
 * read and given a suggested match, and only then acknowledged, so anything that fails comes back on the next run and
 * resumes where it stopped.
 */
class FetchReceivedEInvoices
{
    use AsAction;

    public string $commandSignature = 'e-invoices:fetch-received {organisation : slug of the organisation the SOFTIP account belongs to}';

    /**
     * @return array{received: int, acknowledged: int, failed: int}|null null when another run holds the lock or nothing is configured
     */
    public function handle(Organisation $organisation): ?array
    {
        $inbox = app(SoftipInbox::class);
        if (!$inbox->isConfigured()) {
            return null;
        }

        $lock = Cache::lock('e-invoices-fetch-received', 3600);
        if (!$lock->get()) {
            return null;
        }

        try {
            $stats = ['received' => 0, 'acknowledged' => 0, 'failed' => 0];
            foreach ($inbox->pending() as $transaction) {
                try {
                    $stats['received']++;
                    $this->receive($inbox, $organisation, $transaction);
                    $stats['acknowledged']++;
                } catch (Throwable $exception) {
                    $stats['failed']++;
                    report($exception);
                }
            }

            return $stats;
        } finally {
            $lock->release();
        }
    }

    /**
     * @param array<string, mixed> $transaction
     */
    public function receive(SoftipInbox $inbox, Organisation $organisation, array $transaction): ReceivedEInvoice
    {
        $transactionId = (string)$transaction['id'];

        $receivedEInvoice = ReceivedEInvoice::firstOrCreate(
            ['provider' => 'softip', 'provider_transaction_id' => $transactionId],
            [
                'group_id'         => $organisation->group_id,
                'organisation_id'  => $organisation->id,
                'transmission_id'  => $this->limit($transaction['transmissionId'] ?? null),
                'sender_peppol_id' => $this->limit($transaction['senderId'] ?? null),
                'sender_name'      => $this->limit($transaction['senderName'] ?? null),
                'received_at'      => isset($transaction['receivedAt']) ? Carbon::parse($transaction['receivedAt']) : now(),
            ]
        );
        $transmissionId = $this->limit($transaction['transmissionId'] ?? null);
        if ($receivedEInvoice->organisation_id !== $organisation->id
            || ($transmissionId && $receivedEInvoice->transmission_id && $receivedEInvoice->transmission_id !== $transmissionId)) {
            throw new EInvoiceProviderException("SOFTIP transaction $transactionId is already stored for another organisation or another transmission; not acknowledged");
        }

        if (!$receivedEInvoice->hasXml()) {
            $receivedEInvoice->storeXml($this->wellFormedXml($inbox->downloadXml($transactionId), $transactionId));
        }
        if (!$receivedEInvoice->document_type && !$receivedEInvoice->parse_error) {
            $this->read($receivedEInvoice);
        }
        if (!$receivedEInvoice->match_data) {
            $receivedEInvoice->update(['match_data' => ['suggestion' => SuggestReceivedEInvoiceMatch::run($receivedEInvoice)]]);
        }
        if (!$receivedEInvoice->hasPdf()) {
            $pdf = $inbox->downloadPdf($transactionId);
            if (!str_starts_with(ltrim(substr($pdf, 0, 1024)), '%PDF-')) {
                throw new EInvoiceProviderException("SOFTIP transaction $transactionId: the PDF download is not a PDF; not acknowledged");
            }
            $receivedEInvoice->storePdf($pdf);
        }

        if (!$receivedEInvoice->acknowledged_at) {
            $inbox->acknowledge($transactionId);
            $receivedEInvoice->update(['acknowledged_at' => now()]);
        }

        return $receivedEInvoice;
    }

    /**
     * An empty, truncated or non-XML answer is never stored: stored documents cannot be replaced, and storing it would
     * acknowledge a document we never got. A well-formed document that is not an invoice is stored and read as an error.
     */
    private function wellFormedXml(string $xml, string $transactionId): string
    {
        $previous = libxml_use_internal_errors(true);
        $wellFormed = trim($xml) !== '' && (new \DOMDocument())->loadXML($xml, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$wellFormed) {
            throw new EInvoiceProviderException("SOFTIP transaction $transactionId: the document download is not well-formed XML; not acknowledged");
        }

        return $xml;
    }

    public function read(ReceivedEInvoice $receivedEInvoice): void
    {
        try {
            $header = ReadUblDocument::run((string)$receivedEInvoice->xmlContent());
        } catch (Throwable $exception) {
            $receivedEInvoice->update(['parse_error' => Str::limit($exception->getMessage(), 2000)]);

            return;
        }

        $date   = fn (?string $value) => $value && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) ? $value : null;
        $amount = fn (?string $value) => $value !== null && is_numeric($value) && abs((float)$value) < 1e15 ? $value : null;

        $receivedEInvoice->update([
            'document_type'           => $header['document_type'],
            'document_number'         => $this->limit($header['document_number']),
            'issue_date'              => $date($header['issue_date']),
            'due_date'                => $date($header['due_date']),
            'currency_code'           => $header['currency_code'] && strlen($header['currency_code']) === 3 ? strtoupper($header['currency_code']) : null,
            'net_amount'              => $amount($header['net_amount']),
            'tax_amount'              => $amount($header['tax_amount']),
            'total_amount'            => $amount($header['total_amount']),
            'payable_amount'          => $amount($header['payable_amount']),
            'supplier_name'           => $this->limit($header['supplier_name']),
            'supplier_vat_number'     => $this->limit($header['supplier_vat_number']),
            'supplier_tax_number'     => $this->limit($header['supplier_tax_number']),
            'supplier_company_number' => $this->limit($header['supplier_company_number']),
            'buyer_reference'         => $this->limit($header['buyer_reference']),
            'order_reference'         => $this->limit($header['order_reference']),
            'billing_reference'       => $this->limit($header['billing_reference']),
            'sender_peppol_id'        => $receivedEInvoice->sender_peppol_id ?? $this->limit($header['supplier_peppol_id']),
        ]);
    }

    private function limit(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : Str::limit((string)$value, 250, '');
    }

    public function asCommand(Command $command): int
    {
        $organisation = Organisation::where('slug', $command->argument('organisation'))->firstOrFail();
        $stats        = $this->handle($organisation);

        if ($stats === null) {
            $command->warn('Skipped: SOFTIP is not configured, the organisation does not e-invoice, or another run is in progress');

            return 0;
        }

        $command->info("Received {$stats['received']}, acknowledged {$stats['acknowledged']}, failed {$stats['failed']}");

        return $stats['failed'] ? 1 : 0;
    }
}
