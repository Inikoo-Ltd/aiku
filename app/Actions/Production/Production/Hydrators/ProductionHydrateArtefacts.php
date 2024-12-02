<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 08 May 2024 12:19:59 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Production\Production\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\Production\Production;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Lorisleiva\Actions\Concerns\AsAction;

class ProductionHydrateArtefacts
{
    use AsAction;
    use WithEnumStats;

    private Production $production;

    public function __construct(Production $production)
    {
        $this->production = $production;
    }

    public function getJobMiddleware(): array
    {
        return [(new WithoutOverlapping($this->production->id))->dontRelease()];
    }


    public function handle(Production $production): void
    {
        $stats = [
            'number_artefacts' => $production->artefacts()->count()
        ];

        $production->stats()->update($stats);
    }
}
