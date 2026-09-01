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
class ArtefactsResource extends JsonResource
{
    public function toArray($request): array
    {

        return [
            'slug'    => $this->slug,
            'code'    => $this->code,
            'name'    => $this->name,
            'artefact_family_name' => $this->artefact_family_name,
            'artefact_family_slug' => $this->artefact_family_slug,
            'tags'    => $this->whenLoaded('tags', fn () => $this->tags->pluck('name')),
            'organisation_name' => $this->organisation_name,
            'organisation_slug' => $this->organisation_slug,
        ];
    }
}
