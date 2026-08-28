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

class ProposeDismissShoppingListItem extends OrgAction
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

        $agentOrganisationId = $this->shoppingListItem->agent?->organisation_id;

        if ($agentOrganisationId) {
            return $request->user()->authTo("procurement.{$agentOrganisationId}.edit");
        }

        return false;
    }

    public function handle(ShoppingListItem $shoppingListItem, array $modelData): ShoppingListItem
    {
        abort_unless($shoppingListItem->state === ShoppingListItemStateEnum::OPEN, 422, 'Only open items can have a dismissal proposed');

        $shoppingListItem->update([
            'state'                       => ShoppingListItemStateEnum::DISMISS_PROPOSED,
            'dismiss_reason'              => $modelData['dismiss_reason'],
            'dismiss_proposed_by_user_id' => $modelData['dismiss_proposed_by_user_id'] ?? request()->user()?->id,
            'dismiss_proposed_at'         => now(),
        ]);

        return $shoppingListItem->refresh();
    }

    public function rules(): array
    {
        return [
            'dismiss_reason' => ['required', 'string'],
        ];
    }

    public function asController(ShoppingListItem $shoppingListItem, ActionRequest $request): ShoppingListItem
    {
        $this->shoppingListItem = $shoppingListItem;
        $this->initialisation($shoppingListItem->organisation, $request);

        return $this->handle($shoppingListItem, $this->validatedData);
    }

    public function action(ShoppingListItem $shoppingListItem, array $modelData): ShoppingListItem
    {
        $this->asAction = true;
        $this->shoppingListItem = $shoppingListItem;
        $this->initialisation($shoppingListItem->organisation, $modelData);

        return $this->handle($shoppingListItem, $this->validatedData);
    }
}
