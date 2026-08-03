<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Mar 2023 01:37:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStockHasOrgSupplierProduct;

use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Models\Goods\StockHasSupplierProduct;
use App\Models\Inventory\OrgStock;
use App\Models\Inventory\OrgStockHasOrgSupplierProduct;
use App\Models\Procurement\OrgSupplierProduct;
use Illuminate\Validation\Validator;

class StoreOrgStockHasOrgSupplierProduct extends OrgAction
{
    use WithNoStrictRules;


    private StockHasSupplierProduct $stockHasSupplierProduct;
    private OrgStock $orgStock;
    private OrgSupplierProduct $orgSupplierProduct;

    public function handle(StockHasSupplierProduct $stockHasSupplierProduct, OrgStock $orgStock, OrgSupplierProduct $orgSupplierProduct, array $modelData): OrgStockHasOrgSupplierProduct
    {
        data_set($modelData, 'org_stock_id', $orgStock->id);
        data_set($modelData, 'org_supplier_product_id', $orgSupplierProduct->id);

        /** @var OrgStockHasOrgSupplierProduct $orgStockHasOrgSupplierProduct */
        $orgStockHasOrgSupplierProduct = $stockHasSupplierProduct->orgStockHasOrgSupplierProducts()->create($modelData);

        return $orgStockHasOrgSupplierProduct;
    }

    public function rules(): array
    {
        $rules = [
            'status'         => ['sometimes', 'boolean'],
            'local_priority' => ['sometimes', 'integer'],
        ];

        if (!$this->strict) {
            $rules = $this->noStrictStoreRules($rules);
        }

        return $rules;
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->orgStock->stock_id != $this->stockHasSupplierProduct->stock_id) {
            $validator->errors()->add(
                'org_stock_id',
                'Org Stock->Stock '.$this->orgStock->id.'->'.$this->orgStock->stock_id.' does not belong to this supplier product'.$this->stockHasSupplierProduct->id
            );
        }
        if ($this->orgSupplierProduct->supplier_product_id != $this->stockHasSupplierProduct->supplier_product_id) {
            $validator->errors()->add(
                'org_supplier_product_id',
                'Org/Supplier Product '.$this->orgSupplierProduct->id.'->'.$this->orgSupplierProduct->supplier_product_id.'   does not belong to this stock'.$this->stockHasSupplierProduct->id
            );
        }
    }


    public function action(StockHasSupplierProduct $stockHasSupplierProduct, OrgStock $orgStock, OrgSupplierProduct $orgSupplierProduct, array $modelData, bool $strict = true): OrgStockHasOrgSupplierProduct
    {
        $this->asAction = true;
        $this->strict   = $strict;

        $this->stockHasSupplierProduct = $stockHasSupplierProduct;
        $this->orgStock                = $orgStock;
        $this->orgSupplierProduct      = $orgSupplierProduct;

        $this->initialisation($orgStock->organisation, $modelData);

        return $this->handle($stockHasSupplierProduct, $orgStock, $orgSupplierProduct, $this->validatedData);
    }


}
