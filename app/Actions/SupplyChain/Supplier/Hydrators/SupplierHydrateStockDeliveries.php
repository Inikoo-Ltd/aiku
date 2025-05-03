<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 May 2024 00:55:27 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SupplyChain\Supplier\Hydrators;

use App\Enums\Procurement\StockDelivery\StockDeliveryStateEnum;
use App\Models\SupplyChain\Supplier;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class SupplierHydrateStockDeliveries implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(Supplier $supplier): string
    {
        return $supplier->id;
    }

    public function handle(Supplier $supplier): void
    {
        $stats = [
            'number_stock_deliveries' => $supplier->stockDeliveries->count(),
        ];

        $stockDeliveryStateCounts = $supplier->stockDeliveries()
            ->selectRaw('state, count(*) as total')
            ->groupBy('state')
            ->pluck('total', 'state')->all();

        foreach (StockDeliveryStateEnum::cases() as $productState) {
            $stats['number_stock_deliveries_state_'.$productState->snake()] = Arr::get($stockDeliveryStateCounts, $productState->value, 0);
        }


        $supplier->stats()->update($stats);
    }


}
