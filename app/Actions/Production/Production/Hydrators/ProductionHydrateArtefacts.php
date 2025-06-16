<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 08 May 2024 12:19:59 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Production\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\Production\Production;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ProductionHydrateArtefacts implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Production $production): string
    {
        return $production->id;
    }

    public function handle(Production $production): void
    {
        $stats = [
            'number_artefacts' => $production->artefacts()->count()
        ];

        $production->stats()->update($stats);
    }
}
