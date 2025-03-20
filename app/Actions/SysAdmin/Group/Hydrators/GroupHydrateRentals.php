<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 02 Jun 2024 10:22:00 Central European Summer Time, Mijas Costa, Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Billables\Rental\RentalStateEnum;
use App\Models\Billables\Rental;
use App\Models\SysAdmin\Group;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateRentals
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

        $stats         = [
            'number_rentals' => $group->rentals()->count(),
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'rentals',
                field: 'state',
                enum: RentalStateEnum::class,
                models: Rental::class,
                where: function ($q) use ($group) {
                    $q->where('group_id', $group->id);
                }
            )
        );

        $group->catalogueStats()->update($stats);


    }

}
