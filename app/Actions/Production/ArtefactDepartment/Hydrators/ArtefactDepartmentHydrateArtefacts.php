<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 02 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ArtefactDepartment\Hydrators;

use App\Models\Production\ArtefactDepartment;
use Lorisleiva\Actions\Concerns\AsAction;

class ArtefactDepartmentHydrateArtefacts
{
    use AsAction;

    public function handle(ArtefactDepartment $artefactDepartment): void
    {
        $artefactDepartment->update([
            'number_artefacts' => $artefactDepartment->artefacts()->count(),
        ]);
    }
}
