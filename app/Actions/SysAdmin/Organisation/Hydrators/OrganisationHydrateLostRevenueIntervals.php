<?php

namespace App\Actions\SysAdmin\Organisation\Hydrators;

use App\Actions\Traits\Hydrators\WithIntervalUniqueJob;
use App\Actions\Traits\WithIntervalsAggregators;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\SysAdmin\Organisation;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationHydrateLostRevenueIntervals implements ShouldBeUnique
{
    use AsAction;
    use WithIntervalsAggregators;
    use WithIntervalUniqueJob;

    public string $jobQueue = 'urgent';
    public string $commandSignature = 'hydrate:organisation-lost-revenue-intervals {organisation}';

    public function getJobUniqueId(Organisation $organisation, ?array $intervals = null, ?array $doPreviousPeriods = null): string
    {
        return $this->getUniqueJobWithInterval($organisation, $intervals, $doPreviousPeriods);
    }

    public function asCommand(Command $command): void
    {
        $organisation = Organisation::where('slug', $command->argument('organisation'))->first();

        $this->handle($organisation);
    }

    public function handle(Organisation $organisation, ?array $intervals = null, ?array $doPreviousPeriods = null): void
    {
        $stats = [];

        $queryBase = Invoice::where('in_process', false)->where('organisation_id', $organisation->id)->where('type', InvoiceTypeEnum::REFUND)->selectRaw('abs(sum(org_net_amount)) as sum_aggregate');
        $stats     = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField: 'lost_revenue_other_amount_org_currency_',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );

        $queryBase = Invoice::where('in_process', false)->where('organisation_id', $organisation->id)->where('type', InvoiceTypeEnum::REFUND)->selectRaw('abs(sum(grp_net_amount)) as sum_aggregate');
        $stats     = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField: 'lost_revenue_other_amount_grp_currency_',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );

        $organisation->orderingIntervals()->update($stats);
    }
}
