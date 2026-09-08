<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 08 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Production;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $slug
 * @property string $code
 * @property string $name
 * @property int $number_artefacts
 * @property string|null $artefact_department_name
 * @property string|null $artefact_department_slug
 */
class ArtefactFamiliesResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'                       => $this->id,
            'slug'                     => $this->slug,
            'code'                     => $this->code,
            'name'                     => $this->name,
            'number_artefacts'         => $this->number_artefacts,
            'artefact_department_name' => $this->artefact_department_name,
            'artefact_department_slug' => $this->artefact_department_slug,
        ];
    }
}
