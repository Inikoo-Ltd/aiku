<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 25 Jun 2024 22:26:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Procurement;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $org_supplier_slug
 * @property string $code
 * @property string $name
 * @property string $location
 * @property bool $status
 * @property int $number_org_supplier_products
 * @property int $number_purchase_orders
 * @property int $number_stock_deliveries
 * @property string|null $organisation_name
 */
class OrgSuppliersResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'org_supplier_slug'            => $this->org_supplier_slug,
            'code'                         => $this->code,
            'name'                         => $this->name,
            'location'                     => json_decode($this->location),
            'status_icon'                  => $this->status ? [
                'icon'  => 'fal fa-check',
                'class' => 'text-green-500',
            ] : [
                'icon'  => 'fal fa-times',
                'class' => 'text-red-500',
            ],
            'number_org_supplier_products' => $this->number_org_supplier_products,
            'number_purchase_orders'       => $this->number_purchase_orders,
            'number_stock_deliveries'      => $this->number_stock_deliveries,
            'organisation_name'            => $this->organisation_name,
        ];
    }
}
