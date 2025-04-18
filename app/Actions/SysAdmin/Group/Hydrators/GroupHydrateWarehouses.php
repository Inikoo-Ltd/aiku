<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 06 May 2024 11:58:19 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Inventory\Warehouse\WarehouseStateEnum;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\Group;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateWarehouses implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public function getJobUniqueId(Group $group): string
    {
        return $group->id;
    }


    public function handle(Group $group): void
    {


        $stats = [
            'number_warehouses'                  => $group->warehouses()->count(),
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'warehouses',
                field: 'state',
                enum: WarehouseStateEnum::class,
                models: Warehouse::class,
                where: function ($q) use ($group) {
                    $q->where('group_id', $group->id);
                }
            )
        );

        $group->inventoryStats()->update($stats);
    }
}
