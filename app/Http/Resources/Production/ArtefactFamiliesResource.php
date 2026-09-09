<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 08 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Production;

use App\Enums\Production\Artefact\ArtefactStateEnum;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property int $id
 * @property string $slug
 * @property string $code
 * @property string $name
 * @property int $number_artefacts
 * @property string $state
 * @property int $number_artefacts_without_recipe
 * @property int $number_artefacts_without_batch_size
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
            'number_artefacts_without_recipe'     => $this->number_artefacts_without_recipe,
            'number_artefacts_without_batch_size' => $this->number_artefacts_without_batch_size,
            'state'                    => $this->state ? ArtefactStateEnum::stateIcon()[$this->state instanceof ArtefactStateEnum ? $this->state->value : $this->state] : null,
            'artefact_department_name' => $this->artefact_department_name,
            'artefact_department_slug' => $this->artefact_department_slug,
        ];
    }
}
