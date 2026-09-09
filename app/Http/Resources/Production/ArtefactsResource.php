<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 May 2024 16:10:36 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Production;

use App\Enums\Production\Artefact\ArtefactStateEnum;
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
            'id'      => $this->id,
            'slug'    => $this->slug,
            'code'    => $this->code,
            'name'    => $this->name,
            'state'   => $this->state ? ArtefactStateEnum::stateIcon()[$this->state instanceof ArtefactStateEnum ? $this->state->value : $this->state] : null,
            'artefact_department_name' => $this->artefact_department_name,
            'artefact_department_slug' => $this->artefact_department_slug,
            'artefact_family_name' => $this->artefact_family_name,
            'artefact_family_slug' => $this->artefact_family_slug,
            'recommended_batch_size' => $this->recommended_batch_size,
            'tags'    => $this->whenLoaded('tags', fn () => $this->tags->pluck('name')),
            'organisation_name' => $this->organisation_name,
            'organisation_slug' => $this->organisation_slug,
        ];
    }
}
