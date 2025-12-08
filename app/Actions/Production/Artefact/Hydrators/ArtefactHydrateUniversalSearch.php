<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 14 May 2024 18:42:52 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Artefact\Hydrators;

use App\Models\Production\Artefact;
use Lorisleiva\Actions\Concerns\AsAction;

class ArtefactHydrateUniversalSearch
{
    use AsAction;

    public string $jobQueue = 'universal-search';

    public function handle(Artefact $artefact): void
    {
        $artefact->universalSearch()->updateOrCreate(
            [],
            [
                'group_id' => $artefact->group_id,
                'organisation_id' => $artefact->organisation_id,
                'organisation_slug' => $artefact->organisation->slug,
                'sections' => ['manufacture'],
                'haystack_tier_1' => trim($artefact->name.' '.$artefact->code),
            ]
        );
    }
}
