<?php

/*
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Wix\Product;

use Lorisleiva\Actions\Concerns\AsAction;

class CheckIfWixProductIDIsValid
{
    use AsAction;

    /**
     * Wix product ids are UUIDs.
     */
    public function handle(?string $platformProductId): bool
    {
        if (!$platformProductId) {
            return false;
        }

        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $platformProductId);
    }
}
