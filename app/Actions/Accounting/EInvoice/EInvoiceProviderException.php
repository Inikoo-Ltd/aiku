<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 22 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\EInvoice;

use RuntimeException;

class EInvoiceProviderException extends RuntimeException
{
    /**
     * @param int $httpStatus 0 when the provider could not be reached
     * @param array<string, mixed>|null $payload
     */
    public function __construct(string $message, public readonly int $httpStatus = 0, public readonly ?array $payload = null)
    {
        parent::__construct($message);
    }

    public function isDocumentRefused(): bool
    {
        return $this->httpStatus === 400;
    }

    public function isKeyAlreadyUsed(): bool
    {
        return $this->httpStatus === 409;
    }

    public function isConfigurationProblem(): bool
    {
        return in_array($this->httpStatus, [401, 403], true);
    }
}
