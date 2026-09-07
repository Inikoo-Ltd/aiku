<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 15 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\SupplierProduct\UI;

use App\Models\SupplyChain\SupplierProduct;
use Illuminate\Support\Arr;

trait WithSupplierProductInfo
{
    /**
     * @return array<string, mixed>
     */
    protected function supplierProductInfo(SupplierProduct $supplierProduct): array
    {
        $tradeUnit = $supplierProduct->tradeUnits->first();

        return array_filter([
            'minimum_carton_order' => Arr::get($supplierProduct->data, 'minimum_carton_order'),
            'delivery_time'        => Arr::get($supplierProduct->data, 'delivery_time'),
            'unit_expense'         => Arr::get($supplierProduct->data, 'unit_expense'),
            'extra_costs'          => $supplierProduct->extra_costs,
            'barcode'              => $tradeUnit?->barcode ? (string)$tradeUnit->barcode : null,
            'net_weight'           => $tradeUnit?->net_weight,
            'gross_weight'         => $tradeUnit?->gross_weight,
            'marketing_dimensions' => $tradeUnit?->marketing_dimensions,
        ], fn ($value) => $value !== null && $value !== '');
    }
}
