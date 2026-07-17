<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 17 Apr 2023 11:30:19 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Procurement;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $state
 * @property float $unit_quantity
 * @property float $unit_quantity_checked
 * @property float $unit_quantity_placed
 * @property int $org_stock_id
 * @property string $org_stock_slug
 * @property string $org_stock_code
 * @property string $org_stock_name
 */
class StockDeliveryItemResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                    => $this->id,
            'state'                 => $this->state,
            'unit_quantity'         => $this->unit_quantity,
            'unit_quantity_checked' => $this->unit_quantity_checked,
            'unit_quantity_placed'  => $this->unit_quantity_placed,
            'org_stock_id'          => $this->org_stock_id,
            'org_stock_slug'        => $this->org_stock_slug,
            'org_stock_code'        => $this->org_stock_code,
            'org_stock_name'        => $this->org_stock_name,
        ];
    }
}
