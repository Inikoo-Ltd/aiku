<?php

/*
 * Author: Vika Aqordi <aqordeon@gmail.com>
 * Created: Tue, 16 Sep 2026, Bali, Indonesia
 * Copyright (c) 2026, Inikoo LTD
 */

namespace App\Actions\Production\Artefact\UI;

use App\Actions\Inventory\OrgStock\UI\GetOrgStockLabels;
use App\Models\Production\Artefact;
use Lorisleiva\Actions\Concerns\AsObject;

class GetArtefactLabels
{
    use AsObject;

    /**
     * The factory reaches its labels through the artefact, but they belong to its SKO, so an artefact
     * without one has nothing to show yet.
     */
    public function handle(Artefact $artefact): ?array
    {
        if (!$artefact->orgStock) {
            return null;
        }

        return GetOrgStockLabels::run(
            $artefact->orgStock,
            'grp.models.artefact.',
            ['artefact' => $artefact->id],
            ['edit' => true, 'publish' => true, 'set_mandatory' => false]
        );
    }
}
