<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 03 May 2024 19:26:53 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Enums\Ordering\Order\OrderHandingTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Order\OrderStatusEnum;
use App\Models\SysAdmin\Group;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateOrders implements ShouldBeUnique
{
    use AsAction;

    public string $jobQueue = 'sales';

    public function getJobUniqueId(Group $group): string
    {
        return $group->id;
    }

    public function handle(Group $group): void
    {
        $groups = DB::table('orders')
            ->selectRaw('state, status, handing_type, deleted_at is null as is_live, count(*) as total')
            ->where('group_id', $group->id)
            ->groupByRaw('1, 2, 3, 4')
            ->get();

        $liveGroups = $groups->where('is_live', true);

        $stats = [
            'number_orders' => (int) $groups->sum('total'),
        ];

        foreach (['state' => OrderStateEnum::class, 'status' => OrderStatusEnum::class, 'handing_type' => OrderHandingTypeEnum::class] as $field => $enum) {
            foreach ($enum::cases() as $case) {
                $stats["number_orders_{$field}_".$case->snake()] = (int) $liveGroups->where($field, $case->value)->sum('total');
            }
        }

        $group->orderingStats()->update($stats);
    }
}
