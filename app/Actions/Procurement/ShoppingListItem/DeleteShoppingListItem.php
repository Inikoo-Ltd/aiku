<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\ShoppingListItem;

use App\Actions\OrgAction;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Procurement\ShoppingListItem;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;

class DeleteShoppingListItem extends OrgAction
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    public function handle(ShoppingListItem $shoppingListItem): bool
    {
        abort_unless($shoppingListItem->state === ShoppingListItemStateEnum::OPEN, 422, 'Only open items can be deleted');

        return $shoppingListItem->delete();
    }

    public function asController(ShoppingListItem $shoppingListItem, ActionRequest $request): bool
    {
        $this->initialisation($shoppingListItem->organisation, $request);

        return $this->handle($shoppingListItem);
    }

    public function action(ShoppingListItem $shoppingListItem): bool
    {
        $this->asAction = true;
        $this->initialisation($shoppingListItem->organisation, []);

        return $this->handle($shoppingListItem);
    }

    public function htmlResponse(): \Illuminate\Http\RedirectResponse
    {
        return Redirect::back();
    }
}
