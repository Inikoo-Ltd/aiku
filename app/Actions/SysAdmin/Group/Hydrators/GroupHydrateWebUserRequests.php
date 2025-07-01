<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 29 Jun 2025 09:49:35 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Models\SysAdmin\Group;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateWebUserRequests implements ShouldBeUnique
{
    use AsAction;


    public string $jobQueue = 'low-priority';

    public function getJobUniqueId(int $groupID): string
    {
        return $groupID;
    }

    public function handle(int $groupID): void
    {
        $group = Group::findOrFail($groupID);
        $stats = [
            'number_web_user_requests' => $group->webUserRequests()->count(),
        ];


        $group->webStats->update($stats);
    }


}
