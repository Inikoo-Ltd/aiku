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
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateProductions
{
    use AsAction;
    use WithEnumStats;

    private Group $group;
    public function __construct(Group $group)
    {
        $this->group = $group;
    }

    public function getJobMiddleware(): array
    {
        return [(new WithoutOverlapping($this->group->id))->dontRelease()];
    }


    public function handle(Group $group): void
    {


        $stats = [
            'number_productions'                  => $group->productions()->count(),
        ];


        $stats = array_merge($stats, $this->getEnumStats(
            model:'productions',
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
