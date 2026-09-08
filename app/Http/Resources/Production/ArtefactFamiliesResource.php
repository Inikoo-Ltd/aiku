<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 02 Sep 2026 Malaga, Spain
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
 */
class ArtefactFamiliesResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'               => $this->id,
            'slug'             => $this->slug,
            'code'             => $this->code,
            'name'             => $this->name,
            'number_artefacts' => $this->number_artefacts,
        ];
    }
}
