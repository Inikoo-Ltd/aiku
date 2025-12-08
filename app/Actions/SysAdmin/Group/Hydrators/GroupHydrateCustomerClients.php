<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 20 Apr 2024 22:38:08 Malaysia Time, Kuala Lumpur , Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Models\SysAdmin\Group;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateCustomerClients
{
    use AsAction;

    public string $jobQueue = 'low-priority';

    public function getJobUniqueId(Group $group): string
    {
        return $group->id;
    }

    public function handle(Group $group): void
    {
        $stats = [
            'number_customer_clients' => $group->clients()->count(),
            'number_current_customer_clients' => $group->clients()->where('status', true)->count(),
        ];

        $group->dropshippingStats()->update($stats);
    }
}
