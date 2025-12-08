<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 20 Apr 2024 22:38:08 Malaysia Time, Kuala Lumpur , Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Models\SysAdmin\Group;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateAgents implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(Group $group): string
    {
        return $group->id;
    }

    public function handle(Group $group): void
    {
        $stats = [
            'number_agents' => $group->agents()->count(),
            'number_active_agents' => $group->agents()->where('status', 'true')->count(),
        ];
        $stats['number_archived_agents'] = $stats['number_agents'] - $stats['number_active_agents'];

        $group->supplyChainStats()->update($stats);
    }
}
