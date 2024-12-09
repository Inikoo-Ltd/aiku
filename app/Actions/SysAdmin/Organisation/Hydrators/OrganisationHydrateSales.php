<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 05 Apr 2024 11:19:04 Central Indonesia Time, Bali Office , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation\Hydrators;

use App\Actions\Traits\WithIntervalsAggregators;
use App\Models\Accounting\Invoice;
use App\Models\SysAdmin\Organisation;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationHydrateSales
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

        $queryBase = Invoice::where('organisation_id', $organisation->id)->selectRaw('sum(grp_net_amount) as  sum_group  , sum(grp_net_amount) as  sum_org  ');

        $stats = array_merge($stats, $this->getIntervalStats($queryBase, 'sales_grp_currency_', 'date', 'sum_group'));
        $stats = array_merge($stats, $this->getLastYearIntervalStats($queryBase, 'sales_grp_currency_', 'date', 'sum_group'));

        $stats = array_merge($stats, $this->getIntervalStats($queryBase, 'sales_org_currency_', 'date', 'sum_org'));
        $stats = array_merge($stats, $this->getLastYearIntervalStats($queryBase, 'sales_org_currency_', 'date', 'sum_org'));


        dd($stats);

        $organisation->salesIntervals()->update($stats);
    }


}
