<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Mar 2023 01:59:32 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Asset\Hydrators;

use App\Actions\Traits\WithIntervalsAggregators;
use App\Models\Accounting\InvoiceTransaction;
use App\Models\Catalogue\Asset;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class AssetHydrateSales implements ShouldBeUnique
{
    use AsAction;
    use WithIntervalsAggregators;

    public string $jobQueue = 'sales';

    public function getJobUniqueId(Asset $asset): string
    {
        return $asset->id;
    }

    public function handle(Asset $asset): void
    {
        $stats = [];

        $queryBase = InvoiceTransaction::where('in_process', false)->where('asset_id', $asset->id)->selectRaw('sum(net_amount) as  sum_aggregate  ');
        $stats     = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField: 'sales_'
        );

        $queryBase = InvoiceTransaction::where('in_process', false)->where('asset_id', $asset->id)->selectRaw('sum(grp_net_amount) as  sum_aggregate');
        $stats     = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField: 'sales_grp_currency_'
        );

        $queryBase = InvoiceTransaction::where('in_process', false)->where('asset_id', $asset->id)->selectRaw('sum(org_net_amount) as  sum_aggregate');
        $stats     = $this->getIntervalsData(
            stats: $stats,
            queryBase: $queryBase,
            statField: 'sales_org_currency_'
        );


        $asset->salesIntervals->update($stats);
    }


}
