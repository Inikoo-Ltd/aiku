<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 10:00:00 Central European Summer Time, Mijas, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStockHasOrgSupplierProduct;

use App\Actions\OrgAction;
use App\Actions\SupplyChain\SupplierProduct\StoreSupplierProduct;
use App\Models\Inventory\OrgStock;
use App\Models\Procurement\OrgSupplier;
use App\Models\Procurement\OrgSupplierProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreSupplierProductForOrgStock extends OrgAction
{
    /**
     * @throws \Throwable
     */
    public function handle(OrgStock $orgStock, array $modelData): OrgSupplierProduct
    {
        $orgSupplier = OrgSupplier::findOrFail(Arr::pull($modelData, 'org_supplier_id'));

        $supplierProduct = StoreSupplierProduct::make()->action(
            $orgSupplier->supplier,
            array_merge($modelData, [
                'trade_units' => $orgStock->tradeUnits()->pluck('trade_units.id')->all(),
            ])
        );

        $orgSupplierProduct = OrgSupplierProduct::where('organisation_id', $orgStock->organisation_id)
            ->where('supplier_product_id', $supplierProduct->id)
            ->firstOrFail();

        AttachOrgSupplierProductToOrgStock::make()->action($orgStock, $orgSupplierProduct);

        return $orgSupplierProduct;
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
    }

    public function rules(): array
    {
        return [
            'org_supplier_id'  => [
                'required',
                'integer',
                Rule::exists('org_suppliers', 'id')->where('organisation_id', $this->organisation->id),
            ],
            'code'             => ['required', 'string', 'max:64'],
            'name'             => ['required', 'string', 'max:255'],
            'cost'             => ['required', 'numeric', 'min:0'],
            'units_per_pack'   => ['required', 'numeric', 'gt:0'],
            'units_per_carton' => ['required', 'numeric', 'gt:0'],
        ];
    }

    /**
     * @throws \Throwable
     */
    public function asController(OrgStock $orgStock, ActionRequest $request): RedirectResponse
    {
        $this->initialisation($orgStock->organisation, $request);
        $this->handle($orgStock, $this->validatedData);

        return back();
    }

    /**
     * @throws \Throwable
     */
    public function action(OrgStock $orgStock, array $modelData): OrgSupplierProduct
    {
        $this->asAction = true;
        $this->initialisation($orgStock->organisation, $modelData);

        return $this->handle($orgStock, $this->validatedData);
    }
}
