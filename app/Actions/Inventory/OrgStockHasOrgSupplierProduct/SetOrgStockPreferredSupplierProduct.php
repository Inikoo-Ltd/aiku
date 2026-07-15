<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Wed, 15 Jul 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Inventory\OrgStockHasOrgSupplierProduct;

use App\Actions\OrgAction;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockHasOrgSupplierProduct;
use App\Models\Procurement\OrgSupplierProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class SetOrgStockPreferredSupplierProduct extends OrgAction
{
    private const PREFERRED_PRIORITY = 10;

    public function handle(OrgStock $orgStock, OrgSupplierProduct $orgSupplierProduct): OrgStock
    {
        $isLinked = OrgStockHasOrgSupplierProduct::where('org_stock_id', $orgStock->id)
            ->where('org_supplier_product_id', $orgSupplierProduct->id)
            ->exists();

        if (!$isLinked) {
            throw ValidationException::withMessages([
                'org_supplier_product_id' => __('This supplier product is not linked to this SKU.'),
            ]);
        }

        DB::transaction(function () use ($orgStock, $orgSupplierProduct) {
            OrgStockHasOrgSupplierProduct::where('org_stock_id', $orgStock->id)
                ->update(['local_priority' => 0]);

            OrgStockHasOrgSupplierProduct::where('org_stock_id', $orgStock->id)
                ->where('org_supplier_product_id', $orgSupplierProduct->id)
                ->update(['local_priority' => self::PREFERRED_PRIORITY]);
        });

        return $orgStock;
    }

    public function authorize(ActionRequest $request): bool
    {
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

    public function action(OrgStock $orgStock, OrgSupplierProduct $orgSupplierProduct): OrgStock
    {
        $this->asAction = true;
        $this->initialisation($orgStock->organisation, []);

        return $this->handle($orgStock, $orgSupplierProduct);
    }
}
