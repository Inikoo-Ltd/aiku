<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Meeting;

use App\Actions\Chat\Staff\Call\IssueStaffCallToken;
use App\Models\Chat\StaffCall;
use App\Models\Chat\StaffMeeting;
use App\Models\Chat\StaffMeetingGuest;
use Illuminate\Http\Request;
use Lorisleiva\Actions\Concerns\AsAction;

class IssueStaffMeetingGuestToken
{
    use AsAction;

    /**
     * The media token of a guest someone let in, for their call's room only. Asked again on a reconnect while they
     * are still in; refused once they are out or the call ended.
     *
     * @return array{token: string, url: string, call: array<string, mixed>}
     */
    public function handle(StaffMeetingGuest $guest): array
    {
        $call = $guest->call;
        abort_unless($call && $guest->isInCall($call), 403);

        $guest->update(['last_seen_at' => now()]);

        return [
            'token' => IssueStaffCallToken::make()->forGuest($call, $guest),
            'url'   => config('services.livekit.url'),
            'call'  => self::guestView($call, $guest),
        ];
    }

    /**
     * What a guest's browser knows about the call: enough to draw it, nothing about the conversation or its people.
     *
     * @return array<string, mixed>
     */
    public static function guestView(StaffCall $call, StaffMeetingGuest $guest): array
    {
        return [
            'ulid'               => $call->ulid,
            'conversation_ulid'  => null,
            'conversation_type'  => 'meeting',
            'kind'               => StaffCall::KIND_VIDEO,
            'conversation_name'  => $guest->meeting->title,
            'is_direct'          => false,
            'started_by_user_id' => 0,
            'started_by_name'    => '',
            'started_at'         => $call->started_at?->toIso8601ZuluString(),
            'elapsed_seconds'    => $call->started_at ? (int) $call->started_at->diffInSeconds(now()) : 0,
            'answered_at'        => $call->answered_at?->toIso8601ZuluString() ?? $call->started_at?->toIso8601ZuluString(),
            'ended_at'           => $guest->isInCall($call) ? null : ($call->ended_at ?? now())->toIso8601ZuluString(),
            'outcome'            => null,
            'present_user_ids'   => [],
            'joined_user_ids'    => [],
            'guests_waiting'     => [],
            'guests_in'          => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function asController(StaffMeeting $staffMeeting, string $guest, Request $request): array
    {
        return $this->handle(PollStaffMeetingGuest::resolveGuest($staffMeeting, $guest, $request));
    }
}
