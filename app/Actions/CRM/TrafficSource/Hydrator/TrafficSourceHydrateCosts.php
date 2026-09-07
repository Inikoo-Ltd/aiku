<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 06 Aug 2026
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\TrafficSource\Hydrator;

use App\Models\CRM\TrafficSource;
use App\Models\CRM\TrafficSourceCost;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class TrafficSourceHydrateCosts implements ShouldBeUnique
{
    use AsAction;

    public int $jobUniqueFor = 3600;

    public function getJobUniqueId(TrafficSource $trafficSource): string
    {
        return (string) $trafficSource->id;
    }

    /**
     * Rolls every recorded daily cost for a traffic source up into its stats row, so the listing can
     * sort and total on spend without aggregating the cost table on every page load.
     */
    public function handle(TrafficSource $trafficSource): void
    {
        $totals = TrafficSourceCost::where('traffic_source_id', $trafficSource->id)
            ->select(
                DB::raw('COALESCE(SUM(amount), 0) as total_cost'),
                DB::raw('COALESCE(SUM(org_amount), 0) as org_total_cost')
            )
            ->first();

        $trafficSource->stats()->updateOrCreate(
            ['traffic_source_id' => $trafficSource->id],
            [
                'total_cost'     => $totals->total_cost,
                'org_total_cost' => $totals->org_total_cost,
            ]
        );
    }
}
