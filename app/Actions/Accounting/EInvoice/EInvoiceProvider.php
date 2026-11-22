<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\EInvoice;

use Illuminate\Container\Attributes\Bind;

/**
 * The Peppol access point, kept apart from the UBL so another provider can replace it.
 * Every method throws EInvoiceProviderException when the provider answers with an error or cannot be reached.
 */
#[Bind(SoftipEInvoiceProvider::class)]
interface EInvoiceProvider
{
    public function name(): string;

    public function isConfigured(): bool;

    /**
     * @return array{transaction_id: string, status: string, checksum: string|null}
     */
    public function send(string $xml, string $idempotencyKey, string $tag): array;

    /**
     * @return array{status: string, details: array<string, mixed>}
     */
    public function status(string $transactionId): array;

    /**
     * @return array<int, array{transaction_id: string, status: string}>
     */
    public function findByTag(string $tag): array;

    /**
     * @return array{is_valid: bool, issues: array<int, mixed>}
     */
    public function validate(string $xml): array;

    public function lookupParticipant(string $peppolId): bool;
}
