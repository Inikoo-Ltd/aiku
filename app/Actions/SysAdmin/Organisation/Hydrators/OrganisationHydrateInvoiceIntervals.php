<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 05 Apr 2024 11:19:04 Central Indonesia Time, Bali Office , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation\Hydrators;

use App\Actions\Traits\Hydrators\WithIntervalUniqueJob;
use App\Actions\Traits\WithIntervalsAggregators;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\Accounting\Invoice;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationHydrateInvoiceIntervals implements ShouldBeUnique
{
    use AsAction;
    use WithIntervalsAggregators;
    use WithIntervalUniqueJob;

    public string $jobQueue = 'urgent';

    public function getJobUniqueId(Organisation $organisation, ?array $intervals = null, ?array $doPreviousPeriods = null): string
    {
        return $this->getUniqueJobWithInterval($organisation, $intervals, $doPreviousPeriods);
    }

    public function handle(Organisation $organisation, ?array $intervals = null, ?array $doPreviousPeriods = null): void
    {
        if ($organisation->type != OrganisationTypeEnum::SHOP) {
            return;
        }

        $stats = [];

        $queryBase = Invoice::where('in_process', false)->where('organisation_id', $organisation->id)->where('type', InvoiceTypeEnum::INVOICE)->selectRaw('count(*) as  sum_aggregate');
        $stats     = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField: 'invoices_',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );

        $queryBase = Invoice::where('in_process', false)->where('organisation_id', $organisation->id)->where('type', InvoiceTypeEnum::REFUND)->selectRaw(' count(*) as  sum_aggregate');
        $stats     = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField: 'refunds_',
            intervals: $intervals,
            doPreviousPeriods: $doPreviousPeriods
        );


        $organisation->orderingIntervals()->update($stats);
    }


}
