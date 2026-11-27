<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Meeting;

use App\Events\StaffCallUpdated;
use App\Models\Chat\StaffCall;
use App\Models\Chat\StaffMeeting;
use App\Models\Chat\StaffMeetingGuest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

class KnockOnStaffMeeting
{
    use AsAction;

    /**
     * Someone with no aiku login asks to come in. They wait in the lobby until someone in the call lets them in. The
     * secret is returned once and only its hash is kept: every later request of this guest must present it.
     *
     * @return array{guest: StaffMeetingGuest, secret: string}
     */
    public function handle(StaffMeeting $meeting, string $name, ?string $ip): array
    {
        if (!$meeting->isOpen()) {
            throw ValidationException::withMessages(['name' => __('This meeting has ended.')]);
        }

        if ($meeting->locked_at) {
            throw ValidationException::withMessages(['name' => __('This meeting is locked. Ask the people in it to unlock it.')]);
        }

        $waiting = $meeting->guests()->where('status', StaffMeetingGuest::STATUS_WAITING)->count();
        if ($waiting >= StaffMeeting::MAX_WAITING_GUESTS) {
            throw ValidationException::withMessages(['name' => __('Too many people are waiting to join. Try again in a moment.')]);
        }

        if ($ip !== null && $meeting->guests()->where('status', StaffMeetingGuest::STATUS_WAITING)->where('ip', $ip)->count() >= StaffMeeting::MAX_WAITING_GUESTS_PER_ADDRESS) {
            throw ValidationException::withMessages(['name' => __('Too many people are waiting to join. Try again in a moment.')]);
        }

        $secret = Str::random(48);
        $guest  = $meeting->guests()->create([
            'name'        => $name,
            'secret_hash' => StaffMeetingGuest::hashSecret($secret),
            'status'      => StaffMeetingGuest::STATUS_WAITING,
            'ip'          => $ip,
        ]);

        $call = $meeting->liveCall()->first();
        if ($call) {
            StaffCallUpdated::dispatch($call);
        }

        return ['guest' => $guest, 'secret' => $secret];
    }

    public static function cleanName(string $name): string
    {
        return trim(preg_replace('/\s+/u', ' ', preg_replace('/[\p{C}<>]/u', '', $name)));
    }

    /**
     * @return array{guest: string, secret: string, poll_seconds: int}
     */
    public function asController(StaffMeeting $staffMeeting, Request $request): array
    {
        abort_unless(StaffCall::isEnabled(), 404);

        $request->merge(['name' => self::cleanName((string) $request->input('name'))]);
        $validated = $request->validate(['name' => ['required', 'string', 'min:2', 'max:60']]);

        $result = $this->handle($staffMeeting, $validated['name'], $request->ip());

        return ['guest' => $result['guest']->ulid, 'secret' => $result['secret'], 'poll_seconds' => StaffMeetingGuest::POLL_SECONDS];
    }
}
