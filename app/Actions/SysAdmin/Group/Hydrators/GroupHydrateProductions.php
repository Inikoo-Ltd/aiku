<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 May 2024 12:17:53 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Production\Production\ProductionStateEnum;
use App\Models\Production\Production;
use App\Models\SysAdmin\Group;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateProductions implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Group $group): string
    {
        return $group->id;
    }

    public function handle(Group $group): void
    {

        $stats = [
            'number_productions' => $group->productions()->count(),
        ];

        $stats = array_merge($stats, $this->getEnumStats(
            model: 'productions',
            field: 'state',
            enum: ProductionStateEnum::class,
            models: Production::class,
            where: function ($q) use ($group) {
                $q->where('group_id', $group->id);
            }
        ));

        $group->manufactureStats()->update($stats);
    }
}
