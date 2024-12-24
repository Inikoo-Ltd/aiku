<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 23-12-2024, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2024
 *
*/

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\SysAdmin\Group;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateCustomerBalances
{
    use AsAction;
    use WithEnumStats;

    private Group $group;

    public function __construct(Group $group)
    {
        $this->group = $group;
    }

    public function getJobMiddleware(): array
    {
        return [(new WithoutOverlapping($this->group->id))->dontRelease()];
    }

    public function handle(Group $group): void
    {
        $stats = [];

        $stats['number_customers_with_balances'] = DB::table('customers')
            ->where('group_id', $group->id)
            ->where('balance', '!=', 0)
            ->count();

        $stats['number_customers_with_positive_balances'] = DB::table('customers')
            ->where('group_id', $group->id)
            ->where('balance', '>', 0)
            ->count();

        $stats['number_customers_with_negative_balances'] = DB::table('customers')
            ->where('group_id', $group->id)
            ->where('balance', '<', 0)
            ->count();


        $group->accountingStats->update($stats);
    }


}
