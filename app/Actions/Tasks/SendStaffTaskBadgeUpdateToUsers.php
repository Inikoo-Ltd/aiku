<?php

/*
 * Author: aqordeon <dev@aw-advantage.com>
 * Created: Fri, 02 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Inikoo Ltd
 */

namespace App\Actions\Tasks;

use App\Events\BroadcastStaffTaskBadgeUpdate;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\Concerns\AsAction;

class SendStaffTaskBadgeUpdateToUsers
{
    use AsAction;

    /**
     * @param array<int, int|null> $userIds
     */
    public function handle(array $userIds): void
    {
        User::where('status', true)
            ->whereIn('id', array_values(array_unique(array_filter($userIds))))
            ->get()
            ->each(fn (User $user) => BroadcastStaffTaskBadgeUpdate::dispatch($user->id, GetStaffTaskBadgeData::run($user)));
    }
}
