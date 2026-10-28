<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 26 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierPortalContact;

use App\Models\SupplyChain\SupplierPortalContact;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * An email the supplier no longer has can no longer ask for a link, and the links it was sent stop working now.
 */
class RevokeSupplierPortalContact
{
    use AsObject;

    public function handle(SupplierPortalContact $supplierPortalContact, ?int $userId = null): SupplierPortalContact
    {
        if (!$supplierPortalContact->revoked_at) {
            $supplierPortalContact->update(['revoked_at' => now(), 'revoked_by_user_id' => $userId]);
        }

        $supplierPortalContact->links()->whereNull('revoked_at')->update(['revoked_at' => now(), 'revoked_by_user_id' => $userId]);

        return $supplierPortalContact;
    }
}
