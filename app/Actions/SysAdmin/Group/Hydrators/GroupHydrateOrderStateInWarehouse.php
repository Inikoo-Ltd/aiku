<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 29 Oct 2025 20:34:37 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\SysAdmin\Group;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateOrderStateInWarehouse implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;


    public string $jobQueue = 'sales';

    public function getJobUniqueId(int $groupID): string
    {
        return $groupID;
    }

    public function handle(int $groupID): void
    {
        $group = Group::find($groupID);
        if (!$group) {
            return;
        }
        $stats = [

            'number_orders_state_in_warehouse'              => $group->orders()->where('state', OrderStateEnum::IN_WAREHOUSE)->count(),
            'orders_state_in_warehouse_amount_grp_currency' => $group->orders()->where('state', OrderStateEnum::IN_WAREHOUSE)->sum('grp_net_amount'),

        ];

        $group->orderHandlingStats()->update($stats);
    }


}
