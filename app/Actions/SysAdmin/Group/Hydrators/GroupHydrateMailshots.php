<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 20-12-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\SysAdmin\Group;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateMailshots
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
            'number_mailshots' => $group->mailshots()->count(),
        ];

        $group->commsStats()->updateOrCreate([], $stats);
    }
    public string $commandSignature = 'hydrate:group-mailshots';

    public function asCommand($command): void
    {
        $group = Group::first();
        $this->handle($group);
    }


}
