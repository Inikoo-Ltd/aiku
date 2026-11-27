<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Meeting;

use App\Actions\Chat\Staff\Call\RemoveFromLiveKitRoom;
use App\Events\StaffCallUpdated;
use App\Models\Chat\StaffCall;
use App\Models\Chat\StaffMeetingGuest;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class DecideStaffMeetingGuest
{
    use AsAction;

    public const array DECISIONS = ['admit', 'deny', 'remove'];

    /**
     * Anyone in the call answers a knock; the first answer counts, because only a guest still waiting can be let in or
     * turned away. Removing takes a guest who is in out of the room for good.
     */
    public function handle(StaffCall $call, StaffMeetingGuest $guest, User $user, string $decision): StaffMeetingGuest
    {
        abort_unless($call->ended_at === null && $guest->staff_meeting_id === $call->staff_meeting_id, 404);

        $now = now();

        if ($decision === 'remove') {
            $changed = StaffMeetingGuest::whereKey($guest->id)
                ->where('status', StaffMeetingGuest::STATUS_ADMITTED)
                ->where('staff_call_id', $call->id)
                ->update(['status' => StaffMeetingGuest::STATUS_REMOVED, 'decided_by_user_id' => $user->id, 'decided_at' => $now, 'left_at' => $now, 'updated_at' => $now]);
            if ($changed) {
                RemoveFromLiveKitRoom::dispatch($call, $guest->identity());
            }
        } else {
            $admit   = $decision === 'admit';
            $changed = StaffMeetingGuest::whereKey($guest->id)
                ->where('status', StaffMeetingGuest::STATUS_WAITING)
                ->whereExists(fn ($query) => $query->selectRaw('1')->from('staff_calls')->where('id', $call->id)->whereNull('ended_at'))
                ->update([
                    'status'             => $admit ? StaffMeetingGuest::STATUS_ADMITTED : StaffMeetingGuest::STATUS_DENIED,
                    'staff_call_id'      => $admit ? $call->id : null,
                    'decided_by_user_id' => $user->id,
                    'decided_at'         => $now,
                    'last_seen_at'       => $now,
                    'updated_at'         => $now,
                ]);
            if ($changed && $admit && $call->answered_at === null) {
                $call->update(['answered_at' => $now]);
            }
        }

        if ($changed) {
            StaffCallUpdated::dispatch($call->refresh());
        }

        return $guest->refresh();
    }

    public function authorize(ActionRequest $request): bool
    {
        abort_unless(StaffCall::isEnabled(), 404);

        return self::isInCall($request->route('staffCall'), $request->user());
    }

    public static function isInCall(StaffCall $call, User $user): bool
    {
        return $call->ended_at === null && $call->presentUsers()->where('users.id', $user->id)->exists();
    }

    public function rules(): array
    {
        return ['decision' => ['required', 'string', 'in:'.implode(',', self::DECISIONS)]];
    }

    /**
     * @return array{call: array<string, mixed>}
     */
    public function asController(StaffCall $staffCall, string $guest, ActionRequest $request): array
    {
        $model = StaffMeetingGuest::where('ulid', $guest)->firstOrFail();
        $this->handle($staffCall, $model, $request->user(), $request->validated('decision'));

        return ['call' => $staffCall->refresh()->toBroadcast()];
    }
}
