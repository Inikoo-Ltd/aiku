<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Oct 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Goods;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $stock_id
 * @property int $supplier_product_id
 * @property string $supplier_name
 * @property string $supplier_slug
 * @property string|null $agent_name
 * @property string|null $agent_slug
 * @property string $code
 * @property string $name
 * @property string $description
 * @property string $currency_code
 * @property numeric $unit_cost
 * @property bool $is_preferred
 * @property string|null $preferred_in
 */
class StockSupplierProductsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'stock_id'            => $this->stock_id,
            'supplier_product_id' => $this->supplier_product_id,
            'supplier_name'       => $this->supplier_name,
            'supplier_slug'       => $this->supplier_slug,
            'agent_name'          => $this->agent_name,
            'agent_slug'          => $this->agent_slug,
            'code'                => $this->code,
            'description'         => $this->description ?? $this->name,
            'currency_code'       => $this->currency_code,
            'unit_cost'           => $this->unit_cost,
            'is_preferred'        => (bool) $this->is_preferred,
            'preferred_in'        => $this->preferred_in,
        ];
    }
}
