<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Wed, 10 May 2023 14:06:16 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace Database\Factories\Procurement;

use App\Models\SupplyChain\SupplierProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockDeliveryItemFactory extends Factory
{
    public function definition(): array
    {
        $supplierProduct = SupplierProduct::latest()->first();

        return [
            'group_id' => $supplierProduct->group_id,
            'supplier_product_id' => $supplierProduct->id,
            'unit_quantity' => fake()->numberBetween(1, 100),
        ];
    }
}
