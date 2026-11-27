<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Meeting;

use App\Actions\Chat\Staff\Call\JoinStaffCall;
use App\Models\Chat\StaffCall;
use App\Models\Chat\StaffMeeting;
use App\Models\SysAdmin\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class JoinStaffMeeting
{
    use AsAction;

    /**
     * A colleague with the link: into the running call, or the first one in starts it.
     *
     * @return array{call: StaffCall, token: string, url: string}
     */
    public function handle(StaffMeeting $meeting, User $user): array
    {
        abort_unless(self::mayJoin($meeting, $user), 403);

        $meeting->update(['last_used_at' => now()]);

        $live = $meeting->liveCall()->first();
        if ($live) {
            return JoinStaffCall::run($live, $user);
        }

        abort_if($meeting->for_one_call, 403);

        try {
            $call = StaffCall::create([
                'group_id'           => $meeting->group_id,
                'staff_meeting_id'   => $meeting->id,
                'started_by_user_id' => $user->id,
                'kind'               => StaffCall::KIND_VIDEO,
            ]);
        } catch (UniqueConstraintViolationException) {
            $call = $meeting->liveCall()->firstOrFail();
        }

        return JoinStaffCall::run($call, $user);
    }

    public static function mayJoin(StaffMeeting $meeting, User $user): bool
    {
        return StaffCall::isEnabled()
            && $meeting->isOpen()
            && $user->status
            && $user->group_id === $meeting->group_id;
    }

    public function authorize(ActionRequest $request): bool
    {
        abort_unless(StaffCall::isEnabled(), 404);

        return self::mayJoin($request->route('staffMeeting'), $request->user());
    }

    /**
     * @return array<string, mixed>
     */
    public function asController(StaffMeeting $staffMeeting, ActionRequest $request): array
    {
        $result = $this->handle($staffMeeting, $request->user());

        return ['call' => $result['call']->toBroadcast(), 'token' => $result['token'], 'url' => $result['url']];
    }
}
