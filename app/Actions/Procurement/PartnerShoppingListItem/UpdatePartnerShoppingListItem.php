<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PartnerShoppingListItem;

use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemPriorityEnum;
use App\Actions\Procurement\OrgPartner\Hydrators\OrgPartnerHydrateShoppingListItems;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Procurement\PartnerShoppingListItem;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class UpdatePartnerShoppingListItem extends OrgAction
{
    use WithActionUpdate;

    private PartnerShoppingListItem $partnerShoppingListItem;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    public function handle(PartnerShoppingListItem $partnerShoppingListItem, array $modelData): PartnerShoppingListItem
    {
        abort_unless($partnerShoppingListItem->state === ShoppingListItemStateEnum::OPEN, 422, 'Only open items can be updated');

        $partnerShoppingListItem = $this->update($partnerShoppingListItem, $modelData);
        if ($partnerShoppingListItem->wasChanged('quantity')) {
            OrgPartnerHydrateShoppingListItems::dispatch($partnerShoppingListItem->orgPartner);
        }

        return $partnerShoppingListItem;
    }

    public function rules(): array
    {
        return [
            'quantity' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'priority'       => ['sometimes', 'required', Rule::enum(ShoppingListItemPriorityEnum::class)],
            'needed_by'      => ['sometimes', 'nullable', 'date'],
            'notes'          => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function asController(PartnerShoppingListItem $partnerShoppingListItem, ActionRequest $request): PartnerShoppingListItem
    {
        $this->partnerShoppingListItem = $partnerShoppingListItem;
        $this->initialisation($partnerShoppingListItem->organisation, $request);

        return $this->handle($partnerShoppingListItem, $this->validatedData);
    }

    public function action(PartnerShoppingListItem $partnerShoppingListItem, array $modelData): PartnerShoppingListItem
    {
        $this->asAction = true;
        $this->partnerShoppingListItem = $partnerShoppingListItem;
        $this->initialisation($partnerShoppingListItem->organisation, $modelData);

        return $this->handle($partnerShoppingListItem, $this->validatedData);
    }
}
