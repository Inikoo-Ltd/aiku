<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 11 Aug 2024 12:50:56 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\SupplyChain;

use App\Enums\SupplyChain\SupplierProduct\SupplierProductStateEnum;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $id
 * @property string $code
 * @property string $name
 * @property string $slug
 * @property mixed $agent_slug
 * @property mixed $supplier_slug
 * @property mixed $supplier_name
 * @property SupplierProductStateEnum|null $state
 * @property array<int, array{stock_code: string, state: string}>|null $goods_states
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
            'supplier_name' => $this->whenHas('supplier_name'),
            'state'         => $this->whenHas('state', fn () => $this->state ? [
                'value' => $this->state->value,
                'label' => SupplierProductStateEnum::labels()[$this->state->value],
                'icon'  => SupplierProductStateEnum::stateIcon()[$this->state->value],
            ] : null),
            'goods_states'  => $this->goods_states ?? [],
            'description'   => $this->description,
            'cost'          => $this->cost,
            'currency_code' => $this->currency_code,
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
        ];
    }
}
