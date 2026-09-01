<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\PartnerShoppingListItem;

use App\Actions\Inventory\OrgStock\StoreOrgStock;
use App\Actions\OrgAction;
use App\Actions\Procurement\OrgPartner\GetPartnerOrderCapacity;
use App\Actions\Procurement\OrgPartner\Hydrators\OrgPartnerHydrateShoppingListItems;
use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemPriorityEnum;
use App\Models\Inventory\OrgStock;
use App\Models\Procurement\OrgPartner;
use App\Models\Procurement\PartnerShoppingListItem;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StorePartnerShoppingListItem extends OrgAction
{
    use WithProcurementEditAuthorisation;

    public function handle(OrgPartner $orgPartner, OrgStock $orgStock, array $modelData): PartnerShoppingListItem
    {
        abort_unless(
            in_array($orgStock->organisation_id, [$orgPartner->organisation_id, $orgPartner->partner_id]),
            422,
            'Org stock does not belong to the buying organisation or its partner'
        );

        GetPartnerOrderCapacity::guardAdd($orgPartner, $orgStock);

        $buyerOrgStock = $orgStock;
        if ($orgStock->organisation_id !== $orgPartner->organisation_id) {
            $buyerOrgStock = OrgStock::where('organisation_id', $orgPartner->organisation_id)
                ->where('stock_id', $orgStock->stock_id)
                ->first()
                ?? StoreOrgStock::make()->action($orgPartner->organisation, $orgStock->stock);
        }

        data_set($modelData, 'group_id', $orgPartner->group_id);
        data_set($modelData, 'organisation_id', $orgPartner->organisation_id);
        data_set($modelData, 'org_partner_id', $orgPartner->id);
        data_set($modelData, 'partner_organisation_id', $orgPartner->partner_id);
        data_set($modelData, 'org_stock_id', $buyerOrgStock->id);
        data_set($modelData, 'stock_id', $buyerOrgStock->stock_id);

        if (!isset($modelData['added_by_user_id']) && request()->user()) {
            data_set($modelData, 'added_by_user_id', request()->user()->id);
        }

        $item = PartnerShoppingListItem::create($modelData)->refresh();

        OrgPartnerHydrateShoppingListItems::dispatch($orgPartner);

        return $item;
    }

    public function rules(): array
    {
        return [
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'priority'       => ['sometimes', 'required', Rule::enum(ShoppingListItemPriorityEnum::class)],
            'needed_by'      => ['sometimes', 'nullable', 'date'],
            'notes'          => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function asController(Organisation $organisation, OrgPartner $orgPartner, OrgStock $orgStock, ActionRequest $request): PartnerShoppingListItem
    {
        $this->initialisation($organisation, $request);

        return $this->handle($orgPartner, $orgStock, $this->validatedData);
    }

    public function action(OrgPartner $orgPartner, OrgStock $orgStock, array $modelData): PartnerShoppingListItem
    {
        $this->asAction = true;
        $this->initialisation($orgPartner->organisation, $modelData);

        return $this->handle($orgPartner, $orgStock, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return Redirect::back();
    }
}
