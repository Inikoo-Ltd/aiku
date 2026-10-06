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
     * Stock deliveries still to be placed and the last three received, keyed by org stock id. Quantities are in units.
     *
     * @param  Collection<int, int>  $orgStockIds
     * @return Collection<int, array{coming: Collection<int, array{slug: string, reference: string, state: string, state_label: string, quantity: float}>, last_received: array{slug: string, reference: string, received_at: string, quantity: float}|null, recent_received: Collection<int, array{slug: string, reference: string, received_at: string, quantity: float}>}>
     */
    public function handle(Collection $orgStockIds): Collection
    {
        if ($orgStockIds->isEmpty()) {
            return collect();
        }

        $finishedStates = [
            StockDeliveryStateEnum::BOOKED_IN->value,
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
                        $query->whereIn('stock_deliveries.state', [StockDeliveryStateEnum::BOOKED_IN->value, StockDeliveryStateEnum::PLACED->value])
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
            ->selectRaw("sum(greatest(case when stock_deliveries.state in ('".StockDeliveryStateEnum::CHECKED->value."', '".StockDeliveryStateEnum::BOOKING_IN->value."') then stock_delivery_items.unit_quantity_checked else stock_delivery_items.unit_quantity end - stock_delivery_items.unit_quantity_placed, 0)) as quantity_to_place")
            ->get()
            ->groupBy('org_stock_id');

        $labels = StockDeliveryStateEnum::labels();

        return $lines->map(function (Collection $deliveries) use ($labels) {
            $recentReceived = $deliveries->filter(fn ($delivery) => in_array($delivery->state, [StockDeliveryStateEnum::BOOKED_IN->value, StockDeliveryStateEnum::PLACED->value]) || ($delivery->received_at && $delivery->quantity_placed > 0))
                ->sortByDesc('received_at')
                ->take(3)
                ->map(fn ($delivery) => [
                    'slug'        => $delivery->slug,
                    'reference'   => $delivery->reference,
                    'received_at' => $delivery->received_at,
                    'quantity'    => (float) ($delivery->quantity_placed ?: $delivery->quantity),
                ])
                ->values();

            return [
                'coming'        => $deliveries->whereNotIn('state', [StockDeliveryStateEnum::BOOKED_IN->value, StockDeliveryStateEnum::PLACED->value])
                    ->where('quantity_to_place', '>', 0)
                    ->sortBy('reference')
                    ->map(fn ($delivery) => [
                        'slug'        => $delivery->slug,
                        'reference'   => $delivery->reference,
                        'state'       => $delivery->state,
                        'state_label' => $labels[$delivery->state] ?? $delivery->state,
                        'quantity'    => (float) $delivery->quantity_to_place,
                    ])->values(),
                'last_received'   => $recentReceived->first(),
                'recent_received' => $recentReceived,
            ];
        });
    }
}
