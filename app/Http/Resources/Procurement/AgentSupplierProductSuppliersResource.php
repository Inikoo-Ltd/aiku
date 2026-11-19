<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Procurement;

use App\Models\SupplyChain\SupplierProduct;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SupplierProduct
 *
 * @property string $supplier_code
 * @property string $supplier_name
 * @property string|null $currency_code
 * @property array<int, string> $preferred_in
 * @property bool $is_preferred
 * @property bool $is_current
 * @property array<string, mixed> $supplier_route
 * @property array<string, mixed>|null $product_route
 */
class AgentSupplierProductSuppliersResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                 => $this->id,
            'supplier_code'      => $this->supplier_code,
            'supplier_name'      => $this->supplier_name,
            'code'               => $this->code,
            'name'               => $this->name,
            'cost'               => $this->cost,
            'currency_code'      => $this->currency_code,
            'state'              => $this->state?->value,
            'state_label'        => $this->state?->labels()[$this->state->value] ?? null,
            'availability'       => $this->availability_status?->value,
            'availability_label' => $this->availability_status?->labels()[$this->availability_status->value] ?? null,
            'preferred_in'       => $this->preferred_in,
            'is_preferred'       => $this->is_preferred,
            'is_current'         => $this->is_current,
            'supplier_route'     => $this->supplier_route,
            'product_route'      => $this->product_route,
        ];
    }
}
