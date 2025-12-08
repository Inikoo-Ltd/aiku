<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 10 May 2024 17:29:22 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ManufactureTask\UI;

use App\Http\Resources\Production\ArtefactsResource;
use App\Models\Production\ManufactureTask;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsObject;

class GetManufactureTaskArtefacts
{
    use AsObject;

    public function handle(ManufactureTask $manufactureTask, ActionRequest $request): array
    {
        // Fetch the artefacts related to the manufacture task from the pivot table
        $artefacts = $manufactureTask->artefacts()->get();

        $artefactData = ArtefactsResource::collection($artefacts)->toArray($request);

        return $artefactData;
    }
}
