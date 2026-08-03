<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 11 Aug 2024 13:55:10 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Procurement;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $slug
 * @property mixed $code
 * @property mixed $type
 * @property mixed $name
 * @property mixed $email
 * @property mixed $phone
 * @property mixed $number_org_suppliers
 * @property mixed $number_org_supplier_products
 * @property mixed $number_purchase_orders
 */
class OrgAgentResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'slug'                         => $this->slug,
            'code'                         => $this->agent->code,
            'type'                         => 'Agent',
            'name'                         => $this->agent->name,
            'email'                        => $this->agent->organisation->email,
            'phone'                        => $this->agent->organisation->phone,
            'number_org_suppliers'         => $this->stats->number_org_suppliers,
            'number_org_supplier_products' => $this->stats->number_org_supplier_products,
            'number_purchase_orders'       => $this->stats->number_purchase_orders,
        ];
    }
}
