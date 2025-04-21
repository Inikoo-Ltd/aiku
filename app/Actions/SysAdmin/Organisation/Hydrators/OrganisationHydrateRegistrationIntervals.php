<?php

/*
 * author Arya Permana - Kirin
 * created on 14-03-2025-16h-42m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\SysAdmin\Organisation\Hydrators;

use App\Actions\Traits\WithIntervalsAggregators;
use App\Models\CRM\Customer;
use App\Models\SysAdmin\Organisation;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationHydrateRegistrationIntervals
{
    use AsAction;
    use WithIntervalsAggregators;

    public string $jobQueue = 'sales';

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
        $stats = [];

        $queryBase = Customer::where('organisation_id', $organisation->id)->selectRaw('count(*) as  sum_aggregate');
        $stats     = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField: 'registrations_',
            dateField: 'registered_at'
        );

        $organisation->orderingIntervals()->update($stats);
    }


}
