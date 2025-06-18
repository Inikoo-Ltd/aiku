<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 12 Jun 2024 12:53:40 Central European Summer Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Api\Dropshipping;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $code
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property string $warehouse_area_slug
 * @property mixed $type
 * @property mixed $state
 * @property mixed $organisation_name
 * @property mixed $organisation_code
 * @property mixed $organisation_slug
 */
class ShopsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                => $this->id,
            'slug'              => $this->slug,
            'code'              => $this->code,
            'name'              => $this->name,
            'type'              => $this->type->labels()[$this->type->value],
            'state'             => [
                'label' => $this->state->labels()[$this->state->value],
                'icon'  => $this->state->stateIcon()[$this->state->value]['icon'],
                'class' => $this->state->stateIcon()[$this->state->value]['class']
            ],
            'organisation_name' => $this->organisation_name,
            'organisation_code' => $this->organisation_code,
            'organisation_slug' => $this->organisation_slug,
        ];
    }
}
