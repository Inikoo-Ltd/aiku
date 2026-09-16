<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dropshipping\Bundle;

use App\Models\Catalogue\Product;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Arr;

trait WithBundleTradeUnits
{
    /**
     * A bundle is stocked, weighed and picked through the trade units of its components, so it
     * must carry every one of them, scaled by how many of the component the bundle holds and by
     * how many trade units that component is itself made of.
     *
     * Quantities are added up rather than assigned: two components can share a trade unit, and
     * keying by trade unit alone drops one of them, which is how a component goes missing from a
     * bundle that was built correctly.
     *
     * @param  EloquentCollection<int, Product>  $componentProducts
     * @param  array<int, array{product_id: int, quantity: int|float}>  $componentQuantities
     * @return array<int, array{id: int, quantity: float}>
     */
    public function getBundleTradeUnits(EloquentCollection $componentProducts, array $componentQuantities): array
    {
        $quantityPerTradeUnit = [];

        foreach ($componentProducts as $componentProduct) {
            $componentQuantity = (float) Arr::get(
                collect($componentQuantities)->firstWhere('product_id', $componentProduct->id),
                'quantity',
                1
            );

            foreach ($componentProduct->tradeUnits as $tradeUnit) {
                $tradeUnitsPerComponent = (float) ($tradeUnit->pivot->quantity ?? 1);

                $quantityPerTradeUnit[$tradeUnit->id] = ($quantityPerTradeUnit[$tradeUnit->id] ?? 0)
                    + $tradeUnitsPerComponent * $componentQuantity;
            }
        }

        return array_map(
            fn ($tradeUnitId, $quantity) => ['id' => $tradeUnitId, 'quantity' => $quantity],
            array_keys($quantityPerTradeUnit),
            $quantityPerTradeUnit
        );
    }
}
