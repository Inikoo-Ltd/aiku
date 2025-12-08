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
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateCustomerBalances implements ShouldBeUnique
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
