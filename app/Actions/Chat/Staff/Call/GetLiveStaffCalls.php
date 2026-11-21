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

class GetLiveStaffCalls
{
    use AsAction;

    /**
     * Read once when a page of the app loads, so a call that is ringing or running survives a refresh. It never sweeps
     * stale calls itself (the minute schedule does), so a page load never waits on that.
     *
     * @return array<int, array<string, mixed>>
     */
    public function handle(User $user): array
    {
        $calls = StaffCall::whereNull('ended_at')
            ->whereHas('conversation.activeParticipants', fn ($query) => $query->where('users.id', $user->id))
            ->with('conversation')
            ->get();

        return $calls
            ->filter(fn (StaffCall $call) => $call->mayBeJoinedBy($user))
            ->map(fn (StaffCall $call) => $call->toBroadcast())
            ->values()
            ->all();
    }

    public function authorize(ActionRequest $request): bool
    {
        abort_unless(StaffCall::isEnabled(), 404);

        return true;
    }

    /**
     * @return array{data: array<int, array<string, mixed>>, ping_seconds: int, ring_seconds: int}
     */
    public function asController(ActionRequest $request): array
    {
        return ['data' => $this->handle($request->user()), 'ping_seconds' => StaffCall::PING_SECONDS, 'ring_seconds' => StaffCall::RING_SECONDS];
    }
}
