<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Meeting;

use App\Models\Chat\StaffCall;
use App\Models\Chat\StaffMeeting;
use App\Models\Chat\StaffMeetingGuest;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

class PollStaffMeetingGuest
{
    use AsAction;

    /**
     * The guest browser's heartbeat, in the lobby and in the call. It learns only its own state and whether its call
     * still runs: never who else is there.
     *
     * @return array{status: string, call_live: bool, meeting_open: bool, poll_seconds: int, call: array<string, mixed>|null}
     */
    public function handle(StaffMeetingGuest $guest): array
    {
        if (in_array($guest->status, [StaffMeetingGuest::STATUS_WAITING, StaffMeetingGuest::STATUS_ADMITTED], true)) {
            $guest->update(['last_seen_at' => now()]);
        }

        if ($guest->status === StaffMeetingGuest::STATUS_ADMITTED && ($guest->call === null || $guest->call->ended_at !== null)) {
            $guest->update(['status' => StaffMeetingGuest::STATUS_LEFT, 'left_at' => $guest->left_at ?? now()]);
        }

        $meeting  = $guest->meeting;
        $callLive = $guest->status === StaffMeetingGuest::STATUS_ADMITTED
            ? $guest->call !== null && $guest->call->ended_at === null
            : $meeting->liveCall()->exists();

        return [
            'status'       => $guest->status,
            'call_live'    => $callLive,
            'meeting_open' => $meeting->isOpen(),
            'poll_seconds' => StaffMeetingGuest::POLL_SECONDS,
            'call'         => $guest->call ? IssueStaffMeetingGuestToken::guestView($guest->call, $guest) : null,
        ];
    }

    public static function resolveGuest(StaffMeeting $meeting, string $guestUlid, Request $request): StaffMeetingGuest
    {
        abort_unless(StaffCall::isEnabled(), 404);

        $guest = $meeting->guests()->where('ulid', $guestUlid)->first();
        abort_unless($guest && $guest->holdsSecret($request->input('secret')), 404);

        return $guest;
    }

    /**
     * @return array<string, mixed>
     */
    public function asController(StaffMeeting $staffMeeting, string $guest, Request $request): array
    {
        return $this->handle(self::resolveGuest($staffMeeting, $guest, $request));
    }
}
