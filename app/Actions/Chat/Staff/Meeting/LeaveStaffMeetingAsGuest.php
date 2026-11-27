<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Meeting;

use App\Actions\Chat\Staff\Call\RemoveFromLiveKitRoom;
use App\Events\StaffCallUpdated;
use App\Models\Chat\StaffMeeting;
use App\Models\Chat\StaffMeetingGuest;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Lorisleiva\Actions\Concerns\AsAction;

class LeaveStaffMeetingAsGuest
{
    use AsAction;

    public function handle(StaffMeetingGuest $guest): void
    {
        $wasIn = $guest->status === StaffMeetingGuest::STATUS_ADMITTED;
        if (!in_array($guest->status, [StaffMeetingGuest::STATUS_WAITING, StaffMeetingGuest::STATUS_ADMITTED], true)) {
            return;
        }

        $guest->update(['status' => StaffMeetingGuest::STATUS_LEFT, 'left_at' => now()]);

        $call = $wasIn ? $guest->call : $guest->meeting->liveCall()->first();
        if ($call && $wasIn) {
            RemoveFromLiveKitRoom::dispatch($call, $guest->identity());
        }
        if ($call && $call->ended_at === null) {
            StaffCallUpdated::dispatch($call);
        }
    }

    public function asController(StaffMeeting $staffMeeting, string $guest, Request $request): Response
    {
        $this->handle(PollStaffMeetingGuest::resolveGuest($staffMeeting, $guest, $request));

        return response()->noContent();
    }
}
