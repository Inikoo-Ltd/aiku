<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One attempt to send an invoice or refund as a Peppol e-invoice. The status is the provider's
 * own string (NEW, PREPARING, SENT, DELIVERED, FAILED, REJECTED, INVALID…), plus QUEUED before
 * the provider accepted it and NOT_BUILT when Aiku could not write the document.
 *
 * @property int $id
 * @property int $organisation_id
 * @property int $invoice_id
 * @property string $provider
 * @property string $idempotency_key
 * @property string $tag
 * @property string|null $transaction_id
 * @property string|null $checksum
 * @property string $status
 * @property array<string, mixed>|null $error
 * @property string|null $xml
 * @property Carbon|null $first_attempted_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $status_checked_at
 * @property Carbon|null $final_at
 * @property string|null $alerted_status
 * @property Carbon|null $alerted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Invoice $invoice
 */
class InvoiceEInvoiceTransmission extends Model
{
    public const string QUEUED = 'QUEUED';
    public const string NOT_BUILT = 'NOT_BUILT';
    public const string DELIVERED = 'DELIVERED';

    public const array PROBLEM_STATUSES = [self::NOT_BUILT, 'INVALID', 'FAILED', 'REJECTED'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'error'             => 'array',
            'first_attempted_at' => 'datetime',
            'accepted_at'       => 'datetime',
            'status_checked_at' => 'datetime',
            'final_at'          => 'datetime',
            'alerted_at'        => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class)->withTrashed();
    }

    public function isFinal(): bool
    {
        return $this->status === self::DELIVERED || $this->isProblem();
    }

    public function isProblem(): bool
    {
        return in_array($this->status, self::PROBLEM_STATUSES, true);
    }

    public function errorMessage(): ?string
    {
        return $this->error['message'] ?? null;
    }

    /**
     * @return array{status: string, label: string, tone: string, error: string|null, transaction_id: string|null, updated_at: string|null, attempts: int, send_route: array{name: string, parameters: array<string, int>, method: string}|null}|null
     */
    public static function summaryFor(Invoice $invoice): ?array
    {
        $transmissions = $invoice->eInvoiceTransmissions()->latest('id')->get(['id', 'status', 'error', 'transaction_id', 'updated_at']);
        $latest        = $transmissions->first();
        if (!$latest) {
            return null;
        }

        [$label, $tone] = match ($latest->status) {
            self::DELIVERED => [__('Delivered'), 'success'],
            self::QUEUED    => [__('Waiting to be sent'), 'waiting'],
            self::NOT_BUILT => [__('Not sent, needs fixing'), 'danger'],
            'INVALID'       => [__('Refused as invalid'), 'danger'],
            'FAILED'        => [__('Delivery failed'), 'danger'],
            'REJECTED'      => [__('Refused by the customer'), 'danger'],
            'TEMPORARY_FAILURE' => [__('Delivery being retried'), 'waiting'],
            default         => [__('Sent, waiting for delivery'), 'waiting'],
        };

        return [
            'status'         => $latest->status,
            'label'          => $label,
            'tone'           => $tone,
            'error'          => $latest->errorMessage(),
            'transaction_id' => $latest->transaction_id,
            'updated_at'     => $latest->updated_at?->toIso8601String(),
            'attempts'       => $transmissions->count(),
            'send_route'     => $latest->isProblem() ? ['name' => 'grp.models.invoice.e_invoice.send', 'parameters' => ['invoice' => $invoice->id], 'method' => 'post'] : null,
        ];
    }
}
