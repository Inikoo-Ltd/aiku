<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 25 Jul 2024 20:17:12 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Fulfilment\PalletReturn\PalletReturnStateEnum;
use App\Models\Fulfilment\PalletReturn;
use App\Models\SysAdmin\Group;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydratePalletReturns implements ShouldBeUnique
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
            'number_pallet_returns' => PalletReturn::where('group_id', $group->id)->count()
        ];

        $stats = array_merge($stats, $this->getEnumStats(
            model:'pallet_returns',
            field: 'state',
            enum: PalletReturnStateEnum::class,
            models: PalletReturn::class,
            where: function ($q) use ($group) {
                $q->where('group_id', $group->id);
            }
        ));

        $group->fulfilmentStats()->update($stats);
    }
}
