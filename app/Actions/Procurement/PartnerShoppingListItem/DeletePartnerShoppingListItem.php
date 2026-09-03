<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PartnerShoppingListItem;

use App\Actions\OrgAction;
use App\Actions\Procurement\OrgPartner\Hydrators\OrgPartnerHydrateShoppingListItems;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\SysAdmin\Organisation;
use App\Models\Procurement\OrgPartner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class DeletePartnerShoppingListItem extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    public function handle(PartnerShoppingListItem $partnerShoppingListItem): bool
    {
        abort_unless($partnerShoppingListItem->state === ShoppingListItemStateEnum::OPEN, 422, 'Only open items can be deleted');

        $deleted = $partnerShoppingListItem->delete();

        OrgPartnerHydrateShoppingListItems::dispatch($partnerShoppingListItem->orgPartner);

        return $deleted;
    }

    public function asController(Organisation $organisation, OrgPartner $orgPartner, PartnerShoppingListItem $partnerShoppingListItem, ActionRequest $request): bool
    {
        $this->initialisation($organisation, $request);

        return $this->handle($partnerShoppingListItem);
    }

    public function action(PartnerShoppingListItem $partnerShoppingListItem): bool
    {
        $this->asAction = true;
        $this->initialisation($partnerShoppingListItem->organisation, []);

        return $this->handle($partnerShoppingListItem);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
