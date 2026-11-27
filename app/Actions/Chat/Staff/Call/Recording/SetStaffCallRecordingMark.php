<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 21:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Call\Recording;

use App\Models\Chat\StaffCall;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\Concerns\AsAction;

class SetStaffCallRecordingMark
{
    use AsAction;

    /**
     * The red "Recording" mark lives in the LiveKit room's metadata, so every browser in the call sees it the moment it
     * changes and on joining, guests included, without aiku knowing who is connected. A null user clears it.
     */
    public function handle(StaffCall $call, ?User $startedBy): bool
    {
        $metadata = $startedBy ? json_encode(['recording' => ['by' => $startedBy->chatName()]]) : '';

        return CallLiveKitApi::run('livekit.RoomService/UpdateRoomMetadata', ['room' => $call->room, 'metadata' => $metadata], ['room' => $call->room, 'roomAdmin' => true]) !== null;
    }
}
