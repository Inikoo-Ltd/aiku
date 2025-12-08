<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 16 May 2024 11:24:57 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Production;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property mixed $slug
 * @property mixed $code
 */
class ManufactureTasksResource extends JsonResource
{
    public function toArray($request): array
    {

        return [
            'slug' => $this->slug,
            'code' => $this->code,
            'name' => $this->name,
            'organisation_name' => $this->organisation_name,
            'organisation_slug' => $this->organisation_slug,
        ];
    }
}
