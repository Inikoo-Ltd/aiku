<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 25 Jun 2024 22:26:54 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\SupplyChain;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $slug
 * @property string $code
 * @property string $name
 * @property string $location
 * @property bool $status
 * @property int|null $agent_id
 * @property int $number_supplier_products
 * @property int $number_purchase_orders
 * @property int $number_stock_deliveries
 */
class SuppliersResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                       => $this->id,
            'slug'                     => $this->slug,
            'code'                     => $this->code,
            'name'                     => $this->name,
            'location'                 => $this->location,
            'status_icon'              => $this->getStatusIcon(),
            'number_supplier_products' => $this->number_supplier_products,
            'number_purchase_orders'   => $this->number_purchase_orders,
            'number_stock_deliveries'  => $this->number_stock_deliveries,
        ];
    }

    protected function getStatusIcon(): array
    {
        if (!$this->status) {
            return [
                'icon'    => 'fal fa-archive',
                'class'   => 'text-red-500',
                'tooltip' => __('Archived'),
            ];
        }

        if ($this->agent_id) {
            return [
                'icon'    => 'fal fa-people-arrows',
                'class'   => 'text-blue-500',
                'tooltip' => __('Through Agent'),
            ];
        }

        return [
            'icon'    => 'fal fa-person-dolly',
            'class'   => 'text-green-500',
            'tooltip' => __('Free'),
        ];
    }
}
