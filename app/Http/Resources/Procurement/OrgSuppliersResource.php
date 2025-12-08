<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 25 Jun 2024 22:26:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Procurement;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $code
 * @property string $name
 * @property string $agent_name
 * @property string $slug
 * @property string $org_slug
 * @property string $location
 * @property string $number_org_supplier_products
 * @property string $number_purchase_orders
 * @property string $created_at
 * @property string $updated_at
 * @property string $agent_slug
 */
class OrgSuppliersResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'org_slug' => $this->org_slug,
            'org_supplier_slug' => $this->org_supplier_slug,
            'org_agent_slug' => $this->agent_slug,
            'code' => $this->code,
            'status' => $this->status,
            'status_icon' => $this->status ? [
                'icon' => 'fal fa-check',
                'class' => 'text-green-500',
            ] : [
                'icon' => 'fal fa-times',
                'class' => 'text-red-500',
            ],
            'name' => $this->name,
            'agent_name' => $this->agent_name,
            'number_org_supplier_products' => $this->number_org_supplier_products,
            'number_purchase_orders' => $this->number_purchase_orders,
            'number_purchase_orders_delivery_state_in_process' => $this->number_purchase_orders_delivery_state_in_process,
            'slug' => $this->slug,
            'location' => json_decode($this->location),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
