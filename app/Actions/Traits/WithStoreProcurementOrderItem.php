<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 10 Nov 2024 14:51:13 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Models\Inventory\OrgStock;
use App\Models\Procurement\PurchaseOrder;
use App\Models\Procurement\StockDelivery;
use App\Models\SupplyChain\HistoricSupplierProduct;
use Illuminate\Support\Arr;

trait WithStoreProcurementOrderItem
{
    protected function prepareProcurementOrderItem(PurchaseOrder|StockDelivery $procurementOrder, ?HistoricSupplierProduct $historicSupplierProduct, OrgStock $orgStock, array $modelData): array
    {
        data_set($modelData, 'group_id', $procurementOrder->group_id);
        data_set($modelData, 'organisation_id', $procurementOrder->organisation_id);

        if ($historicSupplierProduct) {
            $supplierProduct = $historicSupplierProduct->supplierProduct;
            data_set($modelData, 'supplier_product_id', $supplierProduct->id);
            data_set($modelData, 'historic_supplier_product_id', $historicSupplierProduct->id);
            $orgSupplierProduct = $supplierProduct->orgSupplierProducts()->where('organisation_id', $procurementOrder->organisation_id)->first();
            data_set($modelData, 'org_supplier_product_id', $orgSupplierProduct->id);

            if (! Arr::has($modelData, 'net_amount')) {
                $unitCost = $orgSupplierProduct->supplierProduct->cost;
                $quantity = $procurementOrder instanceof PurchaseOrder ? $modelData['quantity_ordered'] : $modelData['unit_quantity'];

                data_set(
                    $modelData,
                    'net_amount',
                    $unitCost * $quantity
                );
            }
        }

        data_set($modelData, 'org_stock_id', $orgStock->id);
        data_set($modelData, 'stock_id', $orgStock->stock_id);

        data_set($modelData, 'org_exchange', $procurementOrder->org_exchange, overwrite: false);
        data_set($modelData, 'grp_exchange', $procurementOrder->grp_exchange, overwrite: false);

        data_set($modelData, 'grp_net_amount', Arr::get($modelData, 'net_amount', 0) * Arr::get($modelData, 'grp_exchange', 1));
        data_set($modelData, 'org_net_amount', Arr::get($modelData, 'net_amount', 0) * Arr::get($modelData, 'org_exchange', 1));

        return $modelData;
    }
}
