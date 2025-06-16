<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 May 2023 22:38:15 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\WarehouseArea\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Enums\Fulfilment\Pallet\PalletTypeEnum;
use App\Models\Fulfilment\Pallet;
use App\Models\Inventory\WarehouseArea;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class WarehouseAreaHydratePallets implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(WarehouseArea $warehouseArea): string
    {
        return $warehouseArea->id;
    }

    public function handle(WarehouseArea $warehouseArea): void
    {
        $stats = [
            'number_pallets' => Pallet::where('warehouse_area_id', $warehouseArea->id)->count()
        ];

        $stats = array_merge($stats, $this->getEnumStats(
            model: 'pallets',
            field: 'state',
            enum: PalletStateEnum::class,
            models: Pallet::class,
            where: function ($q) use ($warehouseArea) {
                $q->where('warehouse_area_id', $warehouseArea->id);
            }
        ));

        $stats = array_merge($stats, $this->getEnumStats(
            model: 'pallets',
            field: 'status',
            enum: PalletStatusEnum::class,
            models: Pallet::class,
            where: function ($q) use ($warehouseArea) {
                $q->where('warehouse_area_id', $warehouseArea->id);
            }
        ));

        $stats = array_merge($stats, $this->getEnumStats(
            model: 'pallets',
            field: 'type',
            enum: PalletTypeEnum::class,
            models: Pallet::class,
            where: function ($q) use ($warehouseArea) {
                $q->where('warehouse_area_id', $warehouseArea->id);
            }
        ));


        $warehouseArea->stats()->update($stats);
    }
}
