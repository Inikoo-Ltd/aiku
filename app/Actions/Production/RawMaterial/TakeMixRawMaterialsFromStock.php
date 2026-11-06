<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 06 Nov 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\RawMaterial;

use App\Models\Production\ArtefactManufactureTask;
use App\Models\Production\ManufactureTaskSession;
use Lorisleiva\Actions\Concerns\AsObject;

class TakeMixRawMaterialsFromStock
{
    use AsObject;

    /**
     * A shared mix takes its raw materials out of stock for every kg mixed when it is recorded (HELP-4562),
     * so receiving the lines it went to does not take them again; voiding it puts them back.
     */
    public function handle(ManufactureTaskSession $session, ArtefactManufactureTask $recipeStep, bool $putBack = false): void
    {
        $kgPerArtefact = (float) $recipeStep->units_per_artefact ?: 1;
        $kg            = (float) $session->quantity_made * ($putBack ? -1 : 1);

        $consumption = [];
        foreach ($recipeStep->loadMissing('rawMaterials.rawMaterial.orgStock')->rawMaterials as $recipeStepRawMaterial) {
            $orgStock = $recipeStepRawMaterial->rawMaterial?->orgStock;
            if (!$orgStock) {
                continue;
            }
            $consumption[$orgStock->id] ??= ['orgStock' => $orgStock, 'quantity' => 0.0];
            $consumption[$orgStock->id]['quantity'] += $kg * (float) $recipeStepRawMaterial->quantity_per_unit / $kgPerArtefact;
        }

        DeductProductionRawMaterials::run($session->production, $consumption, $session->user_id);
    }
}
