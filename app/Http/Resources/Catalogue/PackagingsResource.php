<?php

/*
 * Author: Andi Ferdiawan
 * Created: Thu, 09 Jul 2026 11:00:00 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Http\Resources\Catalogue;

use App\Enums\Catalogue\Packaging\PackagingStateEnum;
use App\Enums\Catalogue\Packaging\PackagingTypeEnum;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $slug
 * @property string $family_code
 * @property string $code
 * @property string $name
 * @property PackagingTypeEnum $type
 * @property PackagingStateEnum $state
 * @property numeric $price
 * @property string $currency_code
 * @property int|null $width
 * @property int|null $height
 * @property int|null $depth
 * @property mixed $created_at
 * @property mixed $updated_at
 */
class PackagingsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'            => $this->id,
            'slug'          => $this->slug,
            'family_code'   => $this->family_code,
            'code'          => $this->code,
            'name'          => $this->name,
            'type'          => $this->type->value,
            'type_label'    => $this->type->labels()[$this->type->value],
            'state'         => $this->state->value,
            'state_icon'    => $this->state->stateIcon()[$this->state->value],
            'price'         => $this->price,
            'currency_code' => $this->currency_code,
            'dimensions'    => $this->width && $this->height && $this->depth
                ? "{$this->width} × {$this->height} × {$this->depth} mm"
                : null,
            'leaflets'      => json_decode($this->leaflets_data ?? '[]', true),
            'created_at'    => $this->created_at,
            'updated_at'    => $this->updated_at,
        ];
    }
}
