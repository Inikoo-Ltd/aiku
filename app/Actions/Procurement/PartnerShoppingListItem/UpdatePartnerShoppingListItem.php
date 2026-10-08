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
use App\Models\SysAdmin\Organisation;
use App\Models\Procurement\OrgPartner;
use Illuminate\Validation\Rule;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
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
        $partnerShoppingListItem = DB::transaction(function () use ($partnerShoppingListItem, $modelData) {
            $partnerShoppingListItem = PartnerShoppingListItem::whereKey($partnerShoppingListItem->id)->lockForUpdate()->firstOrFail();

            $this->ensureBuyerCanChange($partnerShoppingListItem);

            if (!Arr::pull($modelData, 'break_batch') && isset($modelData['quantity'])) {
                $modelData['quantity'] = RoundPartnerQuantityToBatches::run($partnerShoppingListItem->orgPartner, $partnerShoppingListItem->stock_id, (float) $modelData['quantity']);
            }

            return $this->update($partnerShoppingListItem, $modelData);
        });

        if ($partnerShoppingListItem->wasChanged('quantity')) {
            OrgPartnerHydrateShoppingListItems::dispatch($partnerShoppingListItem->orgPartner);
        }

        return $partnerShoppingListItem;
    }

    private function ensureBuyerCanChange(PartnerShoppingListItem $partnerShoppingListItem): void
    {
        abort_if($partnerShoppingListItem->pre_picked_at, 422, 'This item is already being prepared by the partner and can no longer be changed');

        $canChange = $this->asAction
            ? in_array($partnerShoppingListItem->state->value, ShoppingListItemStateEnum::onPartnerBuyerList(), true)
            : $partnerShoppingListItem->state === ShoppingListItemStateEnum::DRAFT || $partnerShoppingListItem->isWaitingForPartner();

        abort_unless($canChange, 422, 'The partner has already started this line, it can no longer be changed');
    }

    public function rules(): array
    {
        return [
            'quantity' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'priority'       => ['sometimes', 'required', Rule::enum(ShoppingListItemPriorityEnum::class)],
            'needed_by'      => ['sometimes', 'nullable', 'date'],
            'notes'          => ['sometimes', 'nullable', 'string'],
            'break_batch'    => ['sometimes', 'boolean'],
        ];
    }

    public function asController(Organisation $organisation, OrgPartner $orgPartner, PartnerShoppingListItem $partnerShoppingListItem, ActionRequest $request): PartnerShoppingListItem
    {
        abort_unless($orgPartner->organisation_id === $organisation->id && $partnerShoppingListItem->org_partner_id === $orgPartner->id, 404);
        $this->partnerShoppingListItem = $partnerShoppingListItem;
        $this->initialisation($organisation, $request);

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
