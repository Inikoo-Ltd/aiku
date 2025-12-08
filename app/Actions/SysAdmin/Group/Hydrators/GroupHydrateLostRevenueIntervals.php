<?php

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Actions\Traits\Hydrators\WithIntervalUniqueJob;
use App\Actions\Traits\WithIntervalsAggregators;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\SysAdmin\Group;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateLostRevenueIntervals implements ShouldBeUnique
{
    use AsAction;
    use WithIntervalsAggregators;
    use WithIntervalUniqueJob;

    public string $jobQueue = 'urgent';

    public string $commandSignature = 'hydrate:group-lost-revenue-intervals {group}';

    public function getJobUniqueId(Group $group, ?array $intervals = null, ?array $doPreviousPeriods = null): string
    {
        return $this->getUniqueJobWithInterval($group, $intervals, $doPreviousPeriods);
    }

    public function asCommand(Command $command): void
    {
        $group = Group::where('slug', $command->argument('group'))->first();

        $this->handle($group);
    }

    public function handle(Group $group, ?array $intervals = null, ?array $doPreviousPeriods = null): void
    {
        $stats = [];

        $queryBase = Invoice::where('in_process', false)->where('group_id', $group->id)->where('type', InvoiceTypeEnum::REFUND)->selectRaw('abs(sum(grp_net_amount)) as sum_aggregate');
        $stats = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField: 'lost_revenue_other_amount_grp_currency_',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );

        $group->orderingIntervals()->update($stats);
    }
}
