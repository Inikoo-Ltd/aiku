<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:14:39 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Models\SysAdmin\Group;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateUsers implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(Group $group): string
    {
        return $group->id;
    }

    public function handle(Group $group): void
    {

        $numberUsers = $group->users()->count();
        $numberActiveUsers = $group->users()->where('status', true)->count();

        $stats = [
            'number_users' => $numberUsers,
            'number_users_status_active' => $numberActiveUsers,
            'number_users_status_inactive' => $numberUsers - $numberActiveUsers,

        ];

        $group->sysadminStats->update($stats);
    }
}
