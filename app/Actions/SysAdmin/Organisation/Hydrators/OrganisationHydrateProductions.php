<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 May 2024 12:17:53 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Production\Production\ProductionStateEnum;
use App\Models\Production\Production;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationHydrateProductions implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Organisation $organisation): string
    {
        return $organisation->id;
    }

    public function handle(Organisation $organisation): void
    {

        $stats = [
            'number_productions' => $organisation->productions()->count(),
        ];

        $stats = array_merge($stats, $this->getEnumStats(
            model: 'productions',
            field: 'state',
            enum: ProductionStateEnum::class,
            models: Production::class,
            where: function ($q) use ($organisation) {
                $q->where('organisation_id', $organisation->id);
            }
        ));

        $organisation->manufactureStats()->update($stats);
    }
}
