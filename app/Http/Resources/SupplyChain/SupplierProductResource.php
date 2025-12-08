<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 25 Jun 2024 22:26:46 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\SupplyChain;

use App\Models\SupplyChain\SupplierProduct;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierProductResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var SupplierProduct $supplierProduct */
        $supplierProduct = $this;

        return [
            'code' => $supplierProduct->code,
            'name' => $supplierProduct->name,
            'slug' => $supplierProduct->slug,
            'description' => $supplierProduct->description,
            'created_at' => $supplierProduct->created_at,
            'updated_at' => $supplierProduct->updated_at,

        ];
    }
}
