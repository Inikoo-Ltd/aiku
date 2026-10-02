<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 02 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Procurement;

use App\Actions\Goods\UI\ShowGoodsDashboard;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Splits the SKOs out of stock now into those with stock on the way (open purchase orders and
 * stock deliveries) and those nobody has ordered yet.
 */
class GetStockOutsPipeline
{
    use AsObject;

    /**
     * @return array{out_of_stock: int, in_transit: int, not_ordered: int, arrivals: array<string, int>}
     */
    public function handle(Organisation $organisation, ?string $source = null): array
    {
        $buckets     = GetOrganisationStockCoverBuckets::make();
        $orgStockIds = $buckets->whereBuckets($buckets->scope(DB::table('org_stocks'), $organisation, null, $source), ['out'])
            ->pluck('org_stocks.id')
            ->all();

        $inbound  = ShowGoodsDashboard::make()->inboundByOrgStock($orgStockIds);
        $arrivals = collect($inbound)
            ->countBy(fn (array $line) => isset($line['eta']) ? substr($line['eta'], 0, 7) : 'unknown')
            ->sortKeys()
            ->all();

        return [
            'out_of_stock' => count($orgStockIds),
            'in_transit'   => count($inbound),
            'not_ordered'  => count($orgStockIds) - count($inbound),
            'arrivals'     => $arrivals,
        ];
    }
}
