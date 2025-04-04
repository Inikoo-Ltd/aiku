<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jul 2024 20:22:02 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\Warehouse\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Fulfilment\PalletDelivery\PalletDeliveryStateEnum;
use App\Models\Fulfilment\PalletDelivery;
use App\Models\Inventory\Warehouse;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class WarehouseHydratePalletDeliveries implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public string $jobQueue = 'urgent';

    public function getJobUniqueId(Warehouse $warehouse): string
    {
        return $warehouse->id;
    }

    public function handle(Warehouse $warehouse): void
    {
        $stats = [
            'number_pallet_deliveries' => PalletDelivery::where('warehouse_id', $warehouse->id)->count()
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'pallet_deliveries',
                field: 'state',
                enum: PalletDeliveryStateEnum::class,
                models: PalletDelivery::class,
                where: function ($q) use ($warehouse) {
                    $q->where('warehouse_id', $warehouse->id);
                }
            )
        );


        $warehouse->stats()->update($stats);
    }
}
