<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Aug 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement\ShoppingListItem;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\WithProcurementEditAuthorisation;
use App\Enums\Procurement\ShoppingListItem\ShoppingListItemPriorityEnum;
use App\Models\Procurement\OrgSupplierProduct;
use App\Models\Procurement\ShoppingListItem;
use App\Models\SysAdmin\Organisation;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreShoppingListItem extends OrgAction
{
    use WithProcurementEditAuthorisation;

    public function handle(OrgSupplierProduct $orgSupplierProduct, array $modelData): ShoppingListItem
    {
        $supplierProduct = $orgSupplierProduct->supplierProduct;

        data_set($modelData, 'group_id', $orgSupplierProduct->group_id);
        data_set($modelData, 'organisation_id', $orgSupplierProduct->organisation_id);
        data_set($modelData, 'org_supplier_product_id', $orgSupplierProduct->id);
        data_set($modelData, 'supplier_product_id', $supplierProduct->id);
        data_set($modelData, 'supplier_id', $supplierProduct->supplier_id);
        data_set($modelData, 'agent_id', $orgSupplierProduct->orgAgent?->agent_id ?? $supplierProduct->supplier->agent_id);
        data_set($modelData, 'units_per_pack_snapshot', $supplierProduct->units_per_pack);
        data_set($modelData, 'units_per_carton_snapshot', $supplierProduct->units_per_carton);

        if (!isset($modelData['added_by_user_id']) && request()->user()) {
            data_set($modelData, 'added_by_user_id', request()->user()->id);
        }

        return ShoppingListItem::create($modelData)->refresh();
    }

    public function rules(): array
    {
        return [
            'quantity_units' => ['required', 'numeric', 'min:0.01'],
            'priority'       => ['sometimes', 'required', Rule::enum(ShoppingListItemPriorityEnum::class)],
            'needed_by'      => ['sometimes', 'nullable', 'date'],
            'notes'          => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function asController(Organisation $organisation, OrgSupplierProduct $orgSupplierProduct, ActionRequest $request): ShoppingListItem
    {
        $this->initialisation($organisation, $request);

        return $this->handle($orgSupplierProduct, $this->validatedData);
    }

    public function action(OrgSupplierProduct $orgSupplierProduct, array $modelData): ShoppingListItem
    {
        $this->asAction = true;
        $this->initialisation($orgSupplierProduct->organisation, $modelData);

        return $this->handle($orgSupplierProduct, $this->validatedData);
    }
}
