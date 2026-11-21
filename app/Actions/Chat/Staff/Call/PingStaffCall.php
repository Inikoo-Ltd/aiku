<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Call;

use App\Models\Chat\StaffCall;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class PingStaffCall
{
    use AsAction;

    /**
     * A browser in a call says so every StaffCall::PING_SECONDS; one that stops (closed, crashed, lost) is taken out of the
     * call by CloseStaleStaffCalls, which every ping runs for its own call: whoever is still in closes a call the other side
     * abandoned without waiting for the minute schedule. Someone no longer allowed in the conversation is taken out at once.
     */
    public function handle(StaffCall $call, User $user): StaffCall
    {
        if (!$call->mayBeJoinedBy($user)) {
            $call = LeaveStaffCall::run($call, $user);
            RemoveFromLiveKitRoom::dispatch($call, $user->id);

            return $call;
        }

        $call->users()->newPivotStatement()
            ->where('staff_call_id', $call->id)
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->update(['last_seen_at' => now()]);

        CloseStaleStaffCalls::make()->handle($call->conversation);

        return $call->refresh();
    }

    public function authorize(ActionRequest $request): bool
    {
        abort_unless(StaffCall::isEnabled(), 404);

        return $request->route('staffCall')->users()->where('users.id', $request->user()->id)->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function asController(StaffCall $staffCall, ActionRequest $request): array
    {
        return ['call' => $this->handle($staffCall, $request->user())->toBroadcast()];
    }
}
