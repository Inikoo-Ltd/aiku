<?php

/*
 * author Arya Permana - Kirin
 * created on 14-03-2025-16h-44m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Actions\Traits\Hydrators\WithIntervalUniqueJob;
use App\Actions\Traits\WithIntervalsAggregators;
use App\Models\CRM\Customer;
use App\Models\SysAdmin\Group;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateRegistrationIntervals implements ShouldBeUnique
{
    use AsAction;
    use WithIntervalsAggregators;
    use WithIntervalUniqueJob;

    public string $jobQueue = 'urgent';

    public string $commandSignature = 'hydrate:group-registration-intervals {group}';

    public function getJobUniqueId(int $groupId, ?array $intervals = null, ?array $doPreviousPeriods = null): string
    {
        return $this->getUniqueJobWithIntervalFromId($groupId, $intervals, $doPreviousPeriods);
    }

    public function asCommand(Command $command): void
    {
        $group = Group::where('slug', $command->argument('group'))->first();

        if ($group) {
            $this->handle($group->id);
        }
    }

    public function handle(int $groupId, ?array $intervals = null, ?array $doPreviousPeriods = null): void
    {
        $group = Group::find($groupId);
        if (! $group) {
            return;
        }

        $stats = [];

        $queryBase = Customer::where('group_id', $group->id)->selectRaw('count(*) as  sum_aggregate');
        $stats = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField: 'registrations_',
            dateField: 'registered_at',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );

        $queryBaseWithOrders = Customer::where('group_id', $group->id)
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

        $queryBaseWithoutOrders = Customer::where('group_id', $group->id)
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

        $group->orderingIntervals()->update($stats);
    }
}
