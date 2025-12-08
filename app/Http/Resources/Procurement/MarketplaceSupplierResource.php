<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 12 May 2023 17:20:09 Malaysia Time, Airport, Bali, Id
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Procurement;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $code
 * @property string $name
 * @property string $slug
 * @property string $agent_slug
 * @property string $created_at
 * @property string $updated_at
 * @property numeric $number_suppliers_deliveries
 * @property numeric $number_supplier_products
 * @property numeric $number_purchase_orders
 * @property mixed $adoption
 * @property mixed $location
 */
class MarketplaceSupplierResource extends JsonResource
{
    use HasSelfCall;

    public function toArray($request): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'slug' => $this->slug,
            'agent_slug' => $this->agent_slug,
            'location' => $this->location,
            'number_suppliers_deliveries' => $this->number_suppliers_deliveries,
            'number_supplier_products' => $this->number_supplier_products,
            'number_purchase_orders' => $this->number_purchase_orders,
            'adoption' => $this->adoption ?? 'available',

        ];
    }
}
