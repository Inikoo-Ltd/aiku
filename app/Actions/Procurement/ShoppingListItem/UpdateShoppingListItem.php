<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\ShoppingListItem;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemPriorityEnum;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Procurement\ShoppingListItem;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdateShoppingListItem extends OrgAction
{
    use WithActionUpdate;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if ($this->shoppingListItem->organisation_id === $this->organisation->id) {
            return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
        }

        return $request->user()->authTo('supply-chain.edit');
    }

    private ShoppingListItem $shoppingListItem;

    public function handle(ShoppingListItem $shoppingListItem, array $modelData): ShoppingListItem
    {
        abort_unless($shoppingListItem->state === ShoppingListItemStateEnum::OPEN, 422, 'Only open items can be updated');

        return $this->update($shoppingListItem, $modelData);
    }

    public function rules(): array
    {
        return [
            'quantity_units' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'priority'       => ['sometimes', 'required', Rule::enum(ShoppingListItemPriorityEnum::class)],
            'needed_by'      => ['sometimes', 'nullable', 'date'],
            'notes'          => ['sometimes', 'nullable', 'string'],
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
