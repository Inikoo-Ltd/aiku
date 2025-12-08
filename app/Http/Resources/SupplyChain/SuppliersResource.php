<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 25 Jun 2024 22:26:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\SupplyChain;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $code
 * @property string $name
 * @property string $agent_name
 * @property string $slug
 * @property string $org_slug
 * @property string $location
 * @property string $number_supplier_products
 * @property string $number_purchase_orders
 * @property string $created_at
 * @property string $updated_at
 * @property string $agent_slug
 */
class SuppliersResource extends JsonResource
{
    public function toArray($request): array
    {

        return [
            'org_slug' => $this->org_slug,
            'agent_slug' => $this->agent_slug,
            'code' => $this->code,
            'name' => $this->name,
            'agent_name' => $this->agent_name,
            'number_supplier_products' => $this->number_supplier_products,
            'number_purchase_orders' => $this->number_purchase_orders,
            'slug' => $this->slug,
            'location' => $this->location,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
