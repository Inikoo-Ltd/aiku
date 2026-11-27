<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 16:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Call;

use App\Events\StaffCallUpdated;
use App\Models\Chat\StaffCall;
use App\Models\Chat\StaffCallRecording;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class JoinStaffCall
{
    use AsAction;

    /**
     * The token is issued here and nowhere else. It is signed first, so a call server that cannot sign never leaves
     * anyone rung; the seat is then taken under a lock on the call row, so a join racing a hang-up either lands
     * before the end (and is closed with it) or is refused.
     *
     * @return array{call: StaffCall, token: string, url: string}
     */
    public function handle(StaffCall $call, User $user): array
    {
        abort_unless($call->mayBeJoinedBy($user), 403);

        $token = IssueStaffCallToken::run($call, $user);

        DB::transaction(function () use ($call, $user) {
            $locked = StaffCall::whereKey($call->id)->lockForUpdate()->first();
            abort_if(!$locked || $locked->ended_at, 403);

            $now = now();
            DB::table('staff_call_participants')->upsert(
                [['staff_call_id' => $call->id, 'user_id' => $user->id, 'joined_at' => $now, 'left_at' => null, 'last_seen_at' => $now, 'created_at' => $now, 'updated_at' => $now]],
                ['staff_call_id', 'user_id'],
                ['left_at', 'last_seen_at', 'updated_at']
            );

            if ($user->id !== $locked->started_by_user_id && $locked->answered_at === null) {
                $locked->update(['answered_at' => $now]);
            }
        });

        StaffCallRecording::noteJoined($call, $user);
        $call->refresh()->unsetRelation('users');
        StaffCallUpdated::dispatch($call);

        return ['call' => $call, 'token' => $token, 'url' => config('services.livekit.url')];
    }

    public function authorize(ActionRequest $request): bool
    {
        abort_unless(StaffCall::isEnabled(), 404);

        return $request->route('staffCall')->mayBeJoinedBy($request->user());
    }

    /**
     * @return array<string, mixed>
     */
    public function asController(StaffCall $staffCall, ActionRequest $request): array
    {
        $result = $this->handle($staffCall, $request->user());

        return ['call' => $result['call']->toBroadcast(), 'token' => $result['token'], 'url' => $result['url']];
    }
}
