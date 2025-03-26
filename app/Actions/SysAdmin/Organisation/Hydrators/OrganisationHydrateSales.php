<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 05 Apr 2024 11:19:04 Central Indonesia Time, Bali Office , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation\Hydrators;

use App\Actions\Traits\WithIntervalsAggregators;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationHydrateSales implements ShouldBeUnique
{
    use AsAction;
    use WithIntervalsAggregators;

    public string $jobQueue = 'urgent';

    public function getJobUniqueId(Organisation $organisation, ?array $intervals = null, ?array $doPreviousPeriods = null): string
    {
        $uniqueId = $organisation->id;
        if (!is_null($intervals)) {
            $uniqueId .= '-'.implode('-', $intervals);
        }
        if (!is_null($doPreviousPeriods)) {
            $uniqueId .= '-'.implode('-', $doPreviousPeriods);
        }

        return $uniqueId;
    }


    public function handle(Organisation $organisation, ?array $intervals = null, $doPreviousPeriods = null): void
    {
        if ($organisation->type == OrganisationTypeEnum::AGENT) {
            return;
        }

        $stats = [];

        $queryBase = Invoice::where('in_process', false)->where('organisation_id', $organisation->id)->selectRaw('sum(grp_net_amount) as  sum_aggregate ');
        $stats     = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField:'sales_grp_currency_',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );

        $queryBase = Invoice::where('in_process', false)->where('organisation_id', $organisation->id)->selectRaw(' sum(org_net_amount) as  sum_aggregate  ');
        $stats     = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField:'sales_org_currency_',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );


        $organisation->salesIntervals()->update($stats);
    }


}
