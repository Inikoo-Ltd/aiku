<?php

/*
 * author Arya Permana - Kirin
 * created on 14-03-2025-16h-42m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\SysAdmin\Organisation\Hydrators;

use App\Actions\Traits\Hydrators\WithIntervalUniqueJob;
use App\Actions\Traits\WithIntervalsAggregators;
use App\Models\CRM\Customer;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationHydrateRegistrationIntervals implements ShouldBeUnique
{
    use AsAction;
    use WithIntervalsAggregators;
    use WithIntervalUniqueJob;

    public string $jobQueue = 'urgent';
    public string $commandSignature = 'hydrate:organisation-registration-intervals {organisation}';

    public function getJobUniqueId(int $organisationId, ?array $intervals = null, ?array $doPreviousPeriods = null): string
    {
        return $this->getUniqueJobWithIntervalFromId($organisationId, $intervals, $doPreviousPeriods);
    }

    public function asCommand(Command $command): void
    {
        $organisation = Organisation::where('slug', $command->argument('organisation'))->first();

        if ($organisation) {
            $this->handle($organisation->id);
        }
    }

    public function handle(int $organisationId, ?array $intervals = null, ?array $doPreviousPeriods = null): void
    {
        $organisation = Organisation::find($organisationId);
        if (!$organisation) {
            return;
        }

        $stats = [];

        $queryBase = Customer::where('organisation_id', $organisation->id)->selectRaw('count(*) as  sum_aggregate');
        $stats     = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField: 'registrations_',
            dateField: 'registered_at',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );

        $queryBaseWithOrders = Customer::where('organisation_id', $organisation->id)
            ->join('customer_stats', 'customers.id', '=', 'customer_stats.customer_id')
            ->where('customer_stats.number_orders', '>', 0)
            ->selectRaw('count(*) as sum_aggregate');
        $stats = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBaseWithOrders,
            statField: 'registrations_with_orders_',
            dateField: 'customers.registered_at',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );

        $queryBaseWithoutOrders = Customer::where('organisation_id', $organisation->id)
            ->join('customer_stats', 'customers.id', '=', 'customer_stats.customer_id')
            ->where('customer_stats.number_orders', '=', 0)
            ->selectRaw('count(*) as sum_aggregate');
        $stats = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBaseWithoutOrders,
            statField: 'registrations_without_orders_',
            dateField: 'customers.registered_at',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );

        $organisation->orderingIntervals()->update($stats);
    }


}
