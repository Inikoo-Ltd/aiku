<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Jun 2024 11:50:40 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\CRM\Customer\CustomerStateEnum;
use App\Enums\CRM\Customer\CustomerTradeStateEnum;
use App\Models\CRM\Customer;
use App\Models\SysAdmin\Group;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateCustomers
{
    use AsAction;
    use WithEnumStats;
    private Group $group;

    public string $jobQueue = 'low-priority';

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
        $stats = [
            'number_customers' => $group->customers()->count()
        ];

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'customers',
                field: 'state',
                enum: CustomerStateEnum::class,
                models: Customer::class,
                where: function ($q) use ($group) {
                    $q->where('group_id', $group->id);
                }
            )
        );

        $stats = array_merge(
            $stats,
            $this->getEnumStats(
                model: 'customers',
                field: 'trade_state',
                enum: CustomerTradeStateEnum::class,
                models: Customer::class,
                where: function ($q) use ($group) {
                    $q->where('group_id', $group->id);
                }
            )
        );

        $group->crmStats()->update($stats);
    }
}
