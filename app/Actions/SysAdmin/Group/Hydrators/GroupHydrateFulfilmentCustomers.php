<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 06-02-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\SysAdmin\Group;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateFulfilmentCustomers implements ShouldBeUnique
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
        $stats = [
            'number_customers_interest_pallets_storage' => $group->fulfilmentCustomers()->where('pallets_storage', true)->count(),
            'number_customers_interest_items_storage' => $group->fulfilmentCustomers()->where('items_storage', true)->count(),
            'number_customers_interest_dropshipping' => $group->fulfilmentCustomers()->where('dropshipping', true)->count(),
        ];

        $group->fulfilmentStats()->update($stats);
    }
}
