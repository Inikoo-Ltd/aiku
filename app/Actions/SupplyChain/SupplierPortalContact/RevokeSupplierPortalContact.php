<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 26 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierPortalContact;

use App\Actions\OrgAction;
use App\Models\SupplyChain\SupplierPortalContact;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;

/**
 * The email can no longer ask for a link, and the links it was sent stop working now.
 */
class RevokeSupplierPortalContact extends OrgAction
{
    public function handle(SupplierPortalContact $supplierPortalContact, ?int $userId = null): SupplierPortalContact
    {
        if (!$supplierPortalContact->revoked_at) {
            $supplierPortalContact->update(['revoked_at' => now(), 'revoked_by_user_id' => $userId]);
        }

        $supplierPortalContact->links()->whereNull('revoked_at')->update(['revoked_at' => now(), 'revoked_by_user_id' => $userId]);

        return $supplierPortalContact;
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->authTo('supply-chain.edit');
    }

    public function asController(SupplierPortalContact $supplierPortalContact, ActionRequest $request): SupplierPortalContact
    {
        $this->initialisationFromGroup($supplierPortalContact->group, $request);

        return $this->handle($supplierPortalContact, $request->user()->id);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
