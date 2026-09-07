<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 02 Sep 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\ArtefactFamily\Hydrators;

use App\Models\Production\ArtefactFamily;
use Lorisleiva\Actions\Concerns\AsAction;

class ArtefactFamilyHydrateArtefacts
{
    use AsAction;

    public function handle(ArtefactFamily $artefactFamily): void
    {
        $artefactFamily->update([
            'number_artefacts' => $artefactFamily->artefacts()->count(),
        ]);
    }
}
