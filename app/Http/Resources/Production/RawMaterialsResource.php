<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 May 2024 16:10:36 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Production;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $slug
 * @property mixed $code
 */
class RawMaterialsResource extends JsonResource
{
    public function toArray($request): array
    {

        return [
            'id'          => $this->id,
            'slug'        => $this->slug,
            'code'        => $this->code,
            'description' => $this->description,
            'unit'        => $this->unit,
            'organisation_name' => $this->organisation_name,
            'organisation_slug' => $this->organisation_slug
        ];
    }
}
