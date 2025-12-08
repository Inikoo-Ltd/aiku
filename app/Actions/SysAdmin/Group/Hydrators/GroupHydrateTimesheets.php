<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 27-12-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\SysAdmin\Group;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateTimesheets implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public string $jobQueue = 'low-priority';

    public function getJobUniqueId(Group $group): string
    {
        return $group->id;
    }

    public function handle(Group $group): void
    {

        $queryBase = DB::table('timesheets')
            ->where('group_id', $group->id);

        $stats = [
            'number_timesheets' => $queryBase->clone()->count(),
        ];

        $group->humanResourcesStats->update($stats);
    }

    public string $commandSignature = 'hydrate:group_timesheets';

    public function asCommand($command): void
    {
        $groups = Group::all();

        foreach ($groups as $group) {
            $this->handle($group);
        }
    }
}
