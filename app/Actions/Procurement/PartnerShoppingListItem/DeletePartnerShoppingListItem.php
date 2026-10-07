<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PartnerShoppingListItem;

use App\Actions\Inventory\OrgStock\UpdateOrgStock;
use App\Actions\OrgAction;
use App\Actions\Procurement\OrgPartner\Hydrators\OrgPartnerHydrateShoppingListItems;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemStateEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\SysAdmin\Organisation;
use App\Models\Procurement\OrgPartner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
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

    public function handle(PartnerShoppingListItem $partnerShoppingListItem, bool $stopSuggesting = false): bool
    {
        $deleted = DB::transaction(function () use ($partnerShoppingListItem) {
            $lockedItem = PartnerShoppingListItem::whereKey($partnerShoppingListItem->id)->lockForUpdate()->firstOrFail();

            abort_if($lockedItem->pre_picked_at, 422, 'This item is already being prepared by the partner and can no longer be removed');

            $canRemove = $this->asAction
                ? in_array($lockedItem->state->value, ShoppingListItemStateEnum::onPartnerBuyerList(), true)
                : $lockedItem->state === ShoppingListItemStateEnum::DRAFT || $lockedItem->isWaitingForPartner();

            abort_unless($canRemove, 422, 'The partner has already started this line, it can no longer be removed');

            return $lockedItem->delete();
        });

        if ($stopSuggesting) {
            $ourOrgStock = OrgStock::where('organisation_id', $partnerShoppingListItem->organisation_id)
                ->where('stock_id', $partnerShoppingListItem->stock_id)
                ->first();
            if ($ourOrgStock && !$ourOrgStock->is_excluded_from_auto_ordering) {
                UpdateOrgStock::make()->action($ourOrgStock, ['is_excluded_from_auto_ordering' => true]);
            }
        }

        OrgPartnerHydrateShoppingListItems::dispatch($partnerShoppingListItem->orgPartner);

        return $deleted;
    }

    public function rules(): array
    {
        return [
            'stop_suggesting' => ['sometimes', 'boolean'],
        ];
    }

    public function asController(Organisation $organisation, OrgPartner $orgPartner, PartnerShoppingListItem $partnerShoppingListItem, ActionRequest $request): bool
    {
        abort_unless($orgPartner->organisation_id === $organisation->id && $partnerShoppingListItem->org_partner_id === $orgPartner->id, 404);
        $this->initialisation($organisation, $request);

        return $this->handle($partnerShoppingListItem, (bool) ($this->validatedData['stop_suggesting'] ?? false));
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
