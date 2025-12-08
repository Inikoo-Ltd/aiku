<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 11 Aug 2024 13:55:10 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Procurement;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $code
 * @property string $name
 * @property string $slug
 * @property mixed $location
 * @property numeric $number_org_suppliers
 * @property numeric $number_org_supplier_products
 * @property numeric $number_purchase_orders
 * @property numeric $number_stock_deliveries
 */
class OrgAgentsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'slug' => $this->slug,
            'code' => $this->code,
            'name' => $this->name,
            'location' => json_decode($this->location),
            'number_org_suppliers' => $this->number_org_suppliers,
            'number_org_supplier_products' => $this->number_org_supplier_products,
            'number_purchase_orders' => $this->number_purchase_orders,
            'number_stock_deliveries' => $this->number_stock_deliveries,
        ];
    }
}
