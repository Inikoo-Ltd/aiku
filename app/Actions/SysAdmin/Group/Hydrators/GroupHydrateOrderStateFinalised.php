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
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateOrderStateFinalised implements ShouldBeUnique
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


            'number_orders_state_finalised'              => $group->orders()->where('state', OrderStateEnum::FINALISED)->count(),
            'orders_state_finalised_amount_grp_currency' => $group->orders()->where('state', OrderStateEnum::FINALISED)->sum('grp_net_amount'),


            'number_orders_finalised_today'              => $group->orders()->whereDate('finalised_at', Carbon::today())->count(),
            'orders_finalised_today_amount_grp_currency' => $group->orders()->whereDate('finalised_at', Carbon::today())->sum('grp_net_amount'),


        ];

        $group->orderHandlingStats()->update($stats);
    }


}
