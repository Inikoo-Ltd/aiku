<?php
/*
 * author Arya Permana - Kirin
 * created on 04-11-2024-10h-04m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\SysAdmin\Organisation\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Ordering\Purge\PurgeStateEnum;
use App\Enums\Ordering\Purge\PurgeTypeEnum;
use App\Models\SysAdmin\Organisation;
use App\Models\Ordering\Purge;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationHydratePurges
{
    use AsAction;
    use WithEnumStats;

    private Organisation $organisation;

    public function __construct(Organisation $organisation)
    {
        $this->organisation = $organisation;
    }

    public function getJobMiddleware(): array
    {
        return [(new WithoutOverlapping($this->organisation->id))->dontRelease()];
    }

    public function handle(Organisation $organisation): void
    {
        $stats = [
            'number_purges' => $organisation->orders()->count()
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'purges',
                field: 'state',
                enum: PurgeStateEnum::class,
                models: Purge::class,
                where: function ($q) use ($organisation) {
                    $q->where('organisation_id', $organisation->id);
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
                where: function ($q) use ($organisation) {
                    $q->where('organisation_id', $organisation->id);
                }
            )
        );
        $organisation->salesStats()->update($stats);
    }


}
