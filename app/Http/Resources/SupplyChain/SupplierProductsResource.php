<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 11 Aug 2024 12:50:56 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\SupplyChain;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $id
 * @property string $code
 * @property string $name
 * @property string $slug
 * @property mixed $agent_slug
 * @property mixed $supplier_slug
 * @property string $description
 * @property mixed $cost
 * @property string $currency_code
 * @property string $created_at
 * @property string $updated_at
 */
class SupplierProductsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'code'          => $this->code,
            'name'          => $this->name,
            'slug'          => $this->slug,
            'agent_slug'    => $this->whenHas('agent_slug'),
            'supplier_slug' => $this->whenHas('supplier_slug'),
            'description'   => $this->description,
            'cost'          => $this->cost,
            'currency_code' => $this->currency_code,
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
        ];
    }
}
