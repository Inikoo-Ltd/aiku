<?php

/*
 * author Arya Permana - Kirin
 * created on 03-03-2026
 * github: https://github.com/KirinZero0
 * copyright 2026
 */

namespace App\Actions\Inventory\Warehouse\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;
use App\Models\Inventory\PickingSession;
use App\Models\Inventory\Warehouse;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class WarehouseHydratePickingSessions implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Warehouse $warehouse): string
    {
        return $warehouse->id;
    }

    public function handle(Warehouse $warehouse): void
    {
        $stats = [
            'number_picking_sessions' => PickingSession::where('warehouse_id', $warehouse->id)->count(),
        ];

        $stats = array_merge($stats, $this->getEnumStats(
            model: 'picking_sessions',
            field: 'state',
            enum: PickingSessionStateEnum::class,
            models: PickingSession::class,
            where: function ($q) use ($warehouse) {
                $q->where('warehouse_id', $warehouse->id);
            }
        ));

        $warehouse->stats()->update($stats);
    }
}
