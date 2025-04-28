<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 Jul 2024 16:43:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Catalogue;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property string $slug
 * @property string $code
 * @property mixed $created_at
 * @property mixed $updated_at
 * @property string $name
 * @property string $state
 * @property integer $image_id
 *
 */
class FamilyWebsiteResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'slug'       => $this->slug,
            'image_id'   => $this->image_id,
            'code'       => $this->code,
            'name'       => $this->name,
            'department' => $this->parent?->name,
            'state'      => $this->state,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
