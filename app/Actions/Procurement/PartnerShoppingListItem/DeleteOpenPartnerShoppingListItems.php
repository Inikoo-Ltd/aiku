<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 3 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PartnerShoppingListItem;

use App\Actions\OrgAction;
use App\Actions\Procurement\OrgPartner\Hydrators\OrgPartnerHydrateShoppingListItems;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class DeleteOpenPartnerShoppingListItems extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    /**
     * Only open items go; anything the partner already picked up stays.
     */
    public function handle(OrgPartner $orgPartner): int
    {
        $deleted = PartnerShoppingListItem::query()
            ->where('org_partner_id', $orgPartner->id)
            ->where('state', ShoppingListItemStateEnum::OPEN)
            ->delete();

        OrgPartnerHydrateShoppingListItems::dispatch($orgPartner);

        return $deleted;
    }

    public function asController(Organisation $organisation, OrgPartner $orgPartner, ActionRequest $request): int
    {
        $this->initialisation($organisation, $request);

        return $this->handle($orgPartner);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
