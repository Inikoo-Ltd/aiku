<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 27-12-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Inventory\OrgStock\OrgStockQuantityStatusEnum;
use App\Enums\Inventory\OrgStock\OrgStockStateEnum;
use App\Models\Inventory\OrgStock;
use App\Models\SysAdmin\Group;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateOrgStocks implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public string $jobQueue = 'low-priority';

    public function getJobUniqueId(Group $group): string
    {
        return $group->id;
    }

    public function handle(Group $group): void
    {

        $queryBase = DB::table('org_stocks')
            ->where('group_id', $group->id)
            ->whereNull('deleted_at');

        $stats = [
            'number_org_stocks' => $queryBase->clone()->count(),
            'number_current_org_stocks' => $queryBase->clone()->whereIn('state', [OrgStockStateEnum::ACTIVE->value, OrgStockStateEnum::DISCONTINUING->value])->count(),
            'number_dropped_org_stocks' => $queryBase->clone()->whereIn('state', [OrgStockStateEnum::DISCONTINUED->value, OrgStockStateEnum::ABNORMALITY->value])->count(),
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'org_stocks',
                field: 'state',
                enum: OrgStockStateEnum::class,
                models: OrgStock::class,
                where: function ($q) use ($group) {
                    $q->where('group_id', $group->id);
                }
            )
        );

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'org_stocks',
                field: 'quantity_status',
                enum: OrgStockQuantityStatusEnum::class,
                models: OrgStock::class,
                where: function ($q) use ($group) {
                    $q->where('group_id', $group->id);
                }
            )
        );

        $group->inventoryStats->update($stats);
    }

    public string $commandSignature = 'hydrate:group_org_stocks';

    public function asCommand($command): void
    {
        $groups = Group::all();

        foreach ($groups as $group) {
            $this->handle($group);
        }
    }
}
