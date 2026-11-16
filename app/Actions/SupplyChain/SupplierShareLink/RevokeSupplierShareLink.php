<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 19 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierShareLink;

use App\Actions\OrgAction;
use App\Models\SupplyChain\SupplierShareLink;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;
use App\Actions\Traits\Authorisations\SupplierPortalPermissions;

class RevokeSupplierShareLink extends OrgAction
{
    public function handle(SupplierShareLink $supplierShareLink, ?int $userId = null): SupplierShareLink
    {
        if (!$supplierShareLink->revoked_at) {
            $supplierShareLink->update(['revoked_at' => now(), 'revoked_by_user_id' => $userId]);
        }

        return $supplierShareLink;
    }

    public function authorize(ActionRequest $request): bool
    {
        return SupplierPortalPermissions::canEdit($request->user(), $request->route('supplierShareLink')->supplier);
    }

    public function asController(SupplierShareLink $supplierShareLink, ActionRequest $request): SupplierShareLink
    {
        $this->initialisationFromGroup($supplierShareLink->group, $request);

        return $this->handle($supplierShareLink, $request->user()->id);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
