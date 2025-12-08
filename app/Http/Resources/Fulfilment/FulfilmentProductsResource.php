<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Apr 2024 15:09:20 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Fulfilment;

use App\Models\Catalogue\Asset;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $slug
 * @property string $code
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property string $name
 * @property string $state
 * @property mixed $type
 */
class FulfilmentProductsResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Asset $product */
        $product = $this;

        return [
            'slug' => $this->slug,
            'code' => $this->code,
            'price' => $product->price,
            'currency_code' => $product->currency_code,
            'name' => $this->name,
            'state' => $this->state,
            'state_label' => $product->state->labels()[$product->state->value],
            'state_icon' => $product->state->stateIcon()[$product->state->value],
            'type' => $this->type,
            'type_label' => $product->type->labels()[$product->type->value],
            'type_icon' => $product->type->typeIcon()[$product->type->value],

        ];
    }
}
