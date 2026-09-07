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
use Lorisleiva\Actions\ActionRequest;

class ResolveDismissShoppingListItem extends OrgAction
{
    private ShoppingListItem $shoppingListItem;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if ($request->user()->authTo('supply-chain.edit')) {
            return true;
        }

        return $request->user()->authTo("procurement.{$this->shoppingListItem->organisation_id}.edit");
    }

    public function handle(ShoppingListItem $shoppingListItem, bool $accept): ShoppingListItem
    {
        abort_unless($shoppingListItem->state === ShoppingListItemStateEnum::DISMISS_PROPOSED, 422, 'Only proposed dismissals can be resolved');

        if ($accept) {
            $shoppingListItem->update([
                'state'             => ShoppingListItemStateEnum::DISMISSED,
                'resolved_by_user_id' => request()->user()?->id,
                'resolved_at'       => now(),
            ]);
        } else {
            $shoppingListItem->update([
                'state'                       => ShoppingListItemStateEnum::OPEN,
                'dismiss_reason'              => null,
                'dismiss_proposed_by_user_id' => null,
                'dismiss_proposed_at'         => null,
            ]);
        }

        return $shoppingListItem->refresh();
    }

    public function asController(ShoppingListItem $shoppingListItem, ActionRequest $request): ShoppingListItem
    {
        $this->shoppingListItem = $shoppingListItem;
        $this->initialisation($shoppingListItem->organisation, $request);

        return $this->handle($shoppingListItem, (bool) $request->boolean('accept'));
    }

    public function action(ShoppingListItem $shoppingListItem, bool $accept): ShoppingListItem
    {
        $this->asAction = true;
        $this->shoppingListItem = $shoppingListItem;
        $this->initialisation($shoppingListItem->organisation, []);

        return $this->handle($shoppingListItem, $accept);
    }
}
