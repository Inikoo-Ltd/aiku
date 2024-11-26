<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 10 May 2024 17:29:22 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artefact\UI;

use App\Models\Production\Artefact;
use Lorisleiva\Actions\Concerns\AsObject;

class GetArtefactShowcase
{
    use AsObject;

    public function handle(Artefact $artefact): array
    {
        return [

        ];
    }
}
