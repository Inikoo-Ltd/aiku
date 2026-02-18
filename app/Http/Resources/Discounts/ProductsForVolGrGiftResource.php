<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 Jul 2024 15:08:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Discounts;

use App\Http\Resources\HasSelfCall;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Traits\HasPriceMetrics;
use Illuminate\Support\Arr;

/**
 * @property mixed $slug
 * @property mixed $id
 * @property mixed $name
 * @property mixed $code
 * @property mixed $available_quantity
 * @property mixed $state
 * @property mixed $web_images
 */
class ProductsForVolGrGiftResource extends JsonResource
{
    use HasSelfCall;
    use HasPriceMetrics;

    public function toArray($request): array
    {

        return array(
            'id'                 => $this->id,
            'slug'               => $this->slug,
            'code'               => $this->code,
            'name'               => $this->name,
            'available_quantity' => $this->available_quantity,
            'state'              => $this->state,
            'state_icon'         => $this->state ? $this->state->stateIcon()[$this->state->value] : null,
            'web_images'         => Arr::get($this->web_images, 'main.thumbnail')

        );
    }
}
