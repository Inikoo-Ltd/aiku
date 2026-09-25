<?php

/*
 * Author: Andi Ferdiawan
 * Created: Fri, 10 Jul 2026 10:00:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Http\Resources\Catalogue;

use App\Enums\Catalogue\Leaflet\LeafletStateEnum;
use App\Enums\Catalogue\Leaflet\LeafletTypeEnum;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $name
 * @property LeafletTypeEnum $type
 * @property LeafletStateEnum $state
 * @property numeric $price
 * @property string $currency_code
 * @property array<array-key, string>|null $family_codes
 * @property mixed $created_at
 * @property mixed $updated_at
 */
class LeafletsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'name'           => $this->name,
            'type'           => $this->type->value,
            'type_label'     => $this->type->labels()[$this->type->value],
            'state'          => $this->state->value,
            'state_icon'     => $this->state->stateIcon()[$this->state->value],
            'price'          => $this->price,
            'currency_code'  => $this->currency_code,
            'family_codes'   => $this->family_codes ?? [],
            'created_at'     => $this->created_at,
            'updated_at'     => $this->updated_at,
        ];
    }
}
