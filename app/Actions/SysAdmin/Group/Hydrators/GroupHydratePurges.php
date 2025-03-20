<?php

/*
 * author Arya Permana - Kirin
 * created on 04-11-2024-10h-07m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Ordering\Purge\PurgeStateEnum;
use App\Enums\Ordering\Purge\PurgeTypeEnum;
use App\Models\SysAdmin\Group;
use App\Models\Ordering\Purge;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydratePurges
{
    use AsAction;
    use WithEnumStats;

    private Group $group;
    public string $jobQueue = 'low-priority';
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
            'number_purges' => $group->purges()->count(),
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'purges',
                field: 'state',
                enum: PurgeStateEnum::class,
                models: Purge::class,
                where: function ($q) use ($group) {
                    $q->where('group_id', $group->id);
                }
            )
        );

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'purges',
                field: 'type',
                enum: PurgeTypeEnum::class,
                models: Purge::class,
                where: function ($q) use ($group) {
                    $q->where('group_id', $group->id);
                }
            )
        );

        $group->orderingStats()->update($stats);
    }


}
