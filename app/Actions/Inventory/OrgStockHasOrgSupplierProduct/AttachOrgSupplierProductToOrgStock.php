<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 04 Sep 2026 20:10:00 British Summer Time, Sheffield, UK
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStockHasOrgSupplierProduct;

use App\Actions\OrgAction;
use App\Models\Goods\StockHasSupplierProduct;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockHasOrgSupplierProduct;
use App\Models\Procurement\OrgSupplierProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class AttachOrgSupplierProductToOrgStock extends OrgAction
{
    private const PREFERRED_PRIORITY = 10;

    public function handle(OrgStock $orgStock, OrgSupplierProduct $orgSupplierProduct): OrgStockHasOrgSupplierProduct
    {
        if ($orgSupplierProduct->organisation_id !== $orgStock->organisation_id) {
            throw ValidationException::withMessages([
                'org_supplier_product_id' => __('This supplier product belongs to another organisation.'),
            ]);
        }

        $existing = OrgStockHasOrgSupplierProduct::where('org_stock_id', $orgStock->id)
            ->where('org_supplier_product_id', $orgSupplierProduct->id)
            ->first();
        if ($existing) {
            return $existing;
        }

        $stockHasSupplierProduct = StockHasSupplierProduct::firstOrCreate(
            ['stock_id' => $orgStock->stock_id, 'supplier_product_id' => $orgSupplierProduct->supplier_product_id],
            ['available' => true, 'priority' => 0]
        );

        $isFirstSupplier = !OrgStockHasOrgSupplierProduct::where('org_stock_id', $orgStock->id)->exists();

        return StoreOrgStockHasOrgSupplierProduct::make()->action(
            stockHasSupplierProduct: $stockHasSupplierProduct,
            orgStock: $orgStock,
            orgSupplierProduct: $orgSupplierProduct,
            modelData: [
                'status'         => true,
                'local_priority' => $isFirstSupplier ? self::PREFERRED_PRIORITY : 0,
            ]
        );
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }
        /** @var OrgStock $orgStock */
        $orgStock = $request->route('orgStock');

        return $request->user()->authTo("inventory.{$orgStock->organisation_id}.edit");
    }

    public function asController(OrgStock $orgStock, OrgSupplierProduct $orgSupplierProduct, ActionRequest $request): RedirectResponse
    {
        $this->initialisation($orgStock->organisation, $request);
        $this->handle($orgStock, $orgSupplierProduct);

        return back();
    }

    public function action(OrgStock $orgStock, OrgSupplierProduct $orgSupplierProduct): OrgStockHasOrgSupplierProduct
    {
        $this->asAction = true;
        $this->initialisation($orgStock->organisation, []);

        return $this->handle($orgStock, $orgSupplierProduct);
    }
}
