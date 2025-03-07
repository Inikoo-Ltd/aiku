<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 19 May 2023 22:38:15 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\Warehouse\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Fulfilment\Pallet\PalletStateEnum;
use App\Enums\Fulfilment\Pallet\PalletStatusEnum;
use App\Enums\Fulfilment\Pallet\PalletTypeEnum;
use App\Models\Fulfilment\Pallet;
use App\Models\Inventory\Warehouse;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Lorisleiva\Actions\Concerns\AsAction;

class WarehouseHydratePallets
{
    use AsAction;
    use WithEnumStats;

    private Warehouse $warehouse;
    public function __construct(Warehouse $warehouse)
    {
        $this->warehouse = $warehouse;
    }

    public function getJobMiddleware(): array
    {
        return [(new WithoutOverlapping($this->warehouse->id))->dontRelease()];
    }

    public function handle(Warehouse $warehouse): void
    {
        $stats = [
            'number_pallets' => Pallet::where('warehouse_id', $warehouse->id)->count()
        ];

        $stats = array_merge($stats, $this->getEnumStats(
            model: 'pallets',
            field: 'state',
            enum: PalletStateEnum::class,
            models: Pallet::class,
            where: function ($q) use ($warehouse) {
                $q->where('warehouse_id', $warehouse->id);
            }
        ));

        $stats = array_merge($stats, $this->getEnumStats(
            model: 'pallets',
            field: 'status',
            enum: PalletStatusEnum::class,
            models: Pallet::class,
            where: function ($q) use ($warehouse) {
                $q->where('warehouse_id', $warehouse->id);
            }
        ));

        $stats = array_merge($stats, $this->getEnumStats(
            model: 'pallets',
            field: 'type',
            enum: PalletTypeEnum::class,
            models: Pallet::class,
            where: function ($q) use ($warehouse) {
                $q->where('warehouse_id', $warehouse->id);
            }
        ));


        $warehouse->stats()->update($stats);
    }
}
