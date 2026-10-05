<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\OrgStock;

use App\Enums\GoodsIn\StockDelivery\StockDeliveryStateEnum;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetOrgStocksStockDeliveries
{
    use AsObject;

    /**
     * Stock deliveries still to be placed and the last one received, keyed by org stock id. Quantities are in units.
     *
     * @param  Collection<int, int>  $orgStockIds
     * @return Collection<int, array{coming: Collection<int, array{slug: string, reference: string, state: string, state_label: string, quantity: float}>, last_received: array{slug: string, reference: string, received_at: string, quantity: float}|null}>
     */
    public function handle(Collection $orgStockIds): Collection
    {
        if ($orgStockIds->isEmpty()) {
            return collect();
        }

        $finishedStates = [
            StockDeliveryStateEnum::PLACED->value,
            StockDeliveryStateEnum::CANCELLED->value,
            StockDeliveryStateEnum::NOT_RECEIVED->value,
        ];

        $lines = DB::table('stock_delivery_items')
            ->join('stock_deliveries', 'stock_deliveries.id', 'stock_delivery_items.stock_delivery_id')
            ->whereIn('stock_delivery_items.org_stock_id', $orgStockIds)
            ->whereNull('stock_delivery_items.deleted_at')
            ->whereNull('stock_deliveries.deleted_at')
            ->where(function ($query) use ($finishedStates) {
                $query->whereNotIn('stock_deliveries.state', $finishedStates)
                    ->orWhere(function ($query) {
                        $query->where('stock_deliveries.state', StockDeliveryStateEnum::PLACED->value)
                            ->whereNotNull('stock_deliveries.received_at');
                    });
            })
            ->groupBy('stock_delivery_items.org_stock_id', 'stock_deliveries.id')
            ->select([
                'stock_delivery_items.org_stock_id',
                'stock_deliveries.slug',
                'stock_deliveries.reference',
                'stock_deliveries.state',
                'stock_deliveries.received_at',
            ])
            ->selectRaw('sum(stock_delivery_items.unit_quantity) as quantity')
            ->selectRaw('sum(stock_delivery_items.unit_quantity_placed) as quantity_placed')
            ->get()
            ->groupBy('org_stock_id');

        $labels = StockDeliveryStateEnum::labels();

        return $lines->map(function (Collection $deliveries) use ($labels) {
            $lastReceived = $deliveries->where('state', StockDeliveryStateEnum::PLACED->value)->sortByDesc('received_at')->first();

            return [
                'coming'        => $deliveries->where('state', '!=', StockDeliveryStateEnum::PLACED->value)
                    ->sortBy('reference')
                    ->map(fn ($delivery) => [
                        'slug'        => $delivery->slug,
                        'reference'   => $delivery->reference,
                        'state'       => $delivery->state,
                        'state_label' => $labels[$delivery->state] ?? $delivery->state,
                        'quantity'    => (float) $delivery->quantity,
                    ])->values(),
                'last_received' => $lastReceived ? [
                    'slug'        => $lastReceived->slug,
                    'reference'   => $lastReceived->reference,
                    'received_at' => $lastReceived->received_at,
                    'quantity'    => (float) ($lastReceived->quantity_placed ?: $lastReceived->quantity),
                ] : null,
            ];
        });
    }
}
