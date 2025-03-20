<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 15-10-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Web\Redirect\RedirectTypeEnum;
use App\Models\SysAdmin\Group;
use App\Models\Web\Redirect;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateRedirects
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
            'number_redirects' => $group->redirects()->count(),
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'redirects',
                field: 'type',
                enum: RedirectTypeEnum::class,
                models: Redirect::class,
                where: function ($q) use ($group) {
                    $q->where('group_id', $group->id);
                }
            )
        );

        $group->webStats()->update($stats);
    }
}
