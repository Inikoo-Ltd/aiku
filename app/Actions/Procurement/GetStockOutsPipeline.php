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
 * For each stock cover bucket, how many SKOs already have stock on the way (open purchase orders
 * and stock deliveries) and in which months it lands, and how many wait only on purchase orders or
 * deliveries already past their expected arrival; the rest are not ordered yet. The purchase orders
 * and deliveries counted include the late ones.
 */
class GetStockOutsPipeline
{
    use AsObject;

    /**
     * @return array<string, array{in_transit: int, late: int, purchase_orders: int, stock_deliveries: int, days_min: int|null, days_max: int|null, arrivals: array<string, int>}>
     */
    public function handle(Organisation $organisation, ?string $source = null): array
    {
        $buckets  = GetOrganisationStockCoverBuckets::make();
        $bucketOf = $buckets->scope(DB::table('org_stocks'), $organisation, null, $source)
            ->selectRaw('org_stocks.id, '.$buckets->bucketExpression().' as bucket')
            ->pluck('bucket', 'id')
            ->all();

        $goods = ShowGoodsDashboard::make();
        $lines = collect(array_merge($goods->stockDeliveryInboundLines(array_keys($bucketOf)), $goods->purchaseOrderInboundLines(array_keys($bucketOf))));
        $today = today();

        return $lines->groupBy(fn (array $line) => $bucketOf[$line['org_stock_id']])
            ->map(function ($bucketLines) use ($today) {
                $byOrgStock    = $bucketLines->groupBy('org_stock_id');
                $onSchedule    = $byOrgStock->map(fn ($orgStockLines) => $orgStockLines->reject(fn (array $line) => $line['is_late'] ?? false))->filter->isNotEmpty();
                $firstArrivals = $onSchedule->map(fn ($orgStockLines) => $orgStockLines->pluck('eta')->filter()->min());
                $waitingDays   = $firstArrivals->filter()->map(fn (string $eta) => (int) $today->diffInDays($eta));
                $documents     = $bucketLines->pluck('document')->unique();

                return [
                    'in_transit'       => $onSchedule->count(),
                    'late'             => $byOrgStock->count() - $onSchedule->count(),
                    'purchase_orders'  => $documents->filter(fn (string $document) => str_starts_with($document, 'po:'))->count(),
                    'stock_deliveries' => $documents->filter(fn (string $document) => str_starts_with($document, 'sd:'))->count(),
                    'days_min'         => $waitingDays->min(),
                    'days_max'         => $waitingDays->max(),
                    'arrivals'         => $firstArrivals->countBy(fn (?string $eta) => $eta ? substr($eta, 0, 7) : 'unknown')->sortKeys()->all(),
                ];
            })
            ->all();
    }
}
