<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 20 Dec 2025 00:37:11 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace Database\Factories\GoodsIn;

use App\Models\SupplyChain\SupplierProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockDeliveryItemFactory extends Factory
{
    public function definition(): array
    {
        $supplierProduct = SupplierProduct::latest()->first();

        return [
            'group_id'            => $supplierProduct->group_id,
            'supplier_product_id' => $supplierProduct->id,
            'unit_quantity'       => fake()->numberBetween(1, 100)
        ];
    }
}
