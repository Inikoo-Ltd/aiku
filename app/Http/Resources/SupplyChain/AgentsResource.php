<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 25 Jun 2024 22:26:36 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\SupplyChain;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $code
 * @property string $name
 * @property string $slug
 * @property mixed $location
 * @property numeric $number_suppliers
 * @property numeric $number_supplier_products
 * @property numeric $number_purchase_orders
 */
class AgentsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'slug' => $this->slug,
            'code' => $this->code,
            'name' => $this->name,
            'location' => json_decode($this->location),
            'number_suppliers' => $this->number_suppliers,
            'number_supplier_products' => $this->number_supplier_products,
            'number_purchase_orders' => $this->number_purchase_orders,
        ];
    }
}
