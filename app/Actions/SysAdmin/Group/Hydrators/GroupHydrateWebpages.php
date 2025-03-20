<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 06 Jun 2024 15:26:10 Central European Summer Time, Plane Malaga - Abu Dhabi
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\SysAdmin\Group;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateWebpages
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
            'number_webpages' => $group->webpages()->count(),
        ];

        $group->webStats()->update($stats);
    }
}
