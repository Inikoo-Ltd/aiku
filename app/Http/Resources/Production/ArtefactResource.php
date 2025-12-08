<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 May 2024 14:50:38 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Production;

use App\Models\Production\Artefact;
use Illuminate\Http\Resources\Json\JsonResource;

class ArtefactResource extends JsonResource
{
    public function toArray($request): array
    {
        /** @var Artefact $artefact */
        $artefact = $this;

        return [
            'id' => $artefact->id,
            'slug' => $artefact->slug,
            'name' => $artefact->name,
        ];
    }
}
