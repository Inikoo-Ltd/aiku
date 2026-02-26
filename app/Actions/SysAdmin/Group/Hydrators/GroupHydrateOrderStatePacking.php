<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 25 Feb 2026 20:00:24 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\SysAdmin\Group;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateOrderStatePacking implements ShouldBeUnique
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


            'number_orders_state_packing'              => $group->orderFromActiveShops()->where('state', OrderStateEnum::PACKED)->count(),
            'orders_state_packing_amount_grp_currency' => $group->orderFromActiveShops()->where('state', OrderStateEnum::PACKED)->sum('grp_net_amount'),
            'number_orders_packing_today'              => $group->orderFromActiveShops()->whereDate('packing_at', Carbon::today())->count(),
            'orders_packing_today_amount_grp_currency' => $group->orderFromActiveShops()->whereDate('packing_at', Carbon::today())->sum('grp_net_amount'),


        ];

        $group->orderHandlingStats()->update($stats);
    }


}
