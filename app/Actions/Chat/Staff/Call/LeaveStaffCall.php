<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Call;

use App\Events\StaffCallUpdated;
use App\Models\Chat\StaffCall;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class LeaveStaffCall
{
    use AsAction;

    /**
     * The call ends when its last person leaves, and a direct call ends as soon as either person leaves it, so the one left
     * behind is never kept in an empty call. The starter hanging up before anyone answered cancels it, unless their
     * browser hung up because the ring ran out (ring_timeout): then it is missed, as long as it really rang for about
     * StaffCall::RING_SECONDS by the server's clock, so a caller cannot mark a call missed by saying so early.
     */
    public function handle(StaffCall $call, User $user, bool $ringTimedOut = false): StaffCall
    {
        $call->users()->newPivotStatement()
            ->where('staff_call_id', $call->id)
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->update(['left_at' => now(), 'updated_at' => now()]);

        $call->refresh();
        if ($call->ended_at === null) {
            $isAnsweredDirectCall = $call->answered_at !== null && $call->isDirect();
            if ($isAnsweredDirectCall || !$call->presentUsers()->exists() || $call->shouldEndWhenSomeoneLeaves()) {
                $rangOut = $ringTimedOut
                    && $call->answered_at === null
                    && $user->id === $call->started_by_user_id
                    && $call->started_at->diffInSeconds(now()) >= StaffCall::RING_SECONDS - StaffCall::RING_TIMEOUT_GRACE_SECONDS;
                EndStaffCall::run($call, $rangOut ? StaffCall::OUTCOME_MISSED : null);
            } else {
                StaffCallUpdated::dispatch($call);
            }
        }

        return $call->refresh();
    }

    public function authorize(ActionRequest $request): bool
    {
        abort_unless(StaffCall::isEnabled(), 404);

        return $request->route('staffCall')->users()->where('users.id', $request->user()->id)->exists();
    }

    public function rules(): array
    {
        return ['reason' => ['sometimes', 'nullable', 'string', 'in:ring_timeout']];
    }

    /**
     * @return array<string, mixed>
     */
    public function asController(StaffCall $staffCall, ActionRequest $request): array
    {
        return ['call' => $this->handle($staffCall, $request->user(), $request->validated('reason') === 'ring_timeout')->toBroadcast()];
    }
}
