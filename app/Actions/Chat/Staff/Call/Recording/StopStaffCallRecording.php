<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 21:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Call\Recording;

use App\Events\StaffCallUpdated;
use App\Models\Chat\StaffCall;
use App\Models\Chat\StaffCallRecording;
use App\Models\Chat\StaffCallRecordingTrack;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class StopStaffCallRecording
{
    use AsAction;

    /**
     * Stops once, whoever asks first: Stop in the call, or the call ending (LiveKit then closes the room and its
     * egresses itself). It stops every egress the calls box says is running in the room, not only the ones aiku knows
     * about, and the red mark comes off only once none is left, so nobody is ever recorded without seeing it. Pressing
     * Stop again, or the sweep, retries; with nothing to stop it just takes a stuck mark off. A retry of an earlier Stop
     * ($onlyFinishEarlier, from a late egress report or the sweep) never touches a recording someone started since.
     */
    public function handle(StaffCall $call, ?User $user = null, bool $onlyFinishEarlier = false): void
    {
        $recording = StaffCallRecording::lockFor($call, function () use ($call, $user, $onlyFinishEarlier) {
            $recording = StaffCallRecording::where('staff_call_id', $call->id)->where('status', StaffCallRecording::STATUS_RECORDING)->first();
            if ($recording && $onlyFinishEarlier) {
                return null;
            }
            $recording?->update(['status' => StaffCallRecording::STATUS_STOPPED, 'stopped_at' => now(), 'stopped_by_user_id' => $user?->id]);

            if ($call->ended_at === null && $this->stopEveryEgressIn($call)) {
                StaffCallRecordingTrack::whereHas('recording', fn ($query) => $query->where('staff_call_id', $call->id))
                    ->where('status', StaffCallRecordingTrack::STATUS_ACTIVE)
                    ->update(['status' => StaffCallRecordingTrack::STATUS_STOPPING]);
                SetStaffCallRecordingMark::run($call, null);
            }

            return $recording;
        });

        if ($call->ended_at === null) {
            StaffCallUpdated::dispatch($call);
        }

        if ($recording) {
            ProcessStaffCallRecording::make()->whenTracksAreIn($recording->refresh());
        }
    }

    /** True only once the calls box reports no egress running in the room. */
    public function stopEveryEgressIn(StaffCall $call): bool
    {
        $grant   = ['room' => $call->room, 'roomRecord' => true];
        $running = CallLiveKitApi::run('livekit.Egress/ListEgress', ['room_name' => $call->room, 'active' => true], $grant);
        if ($running === null) {
            return false;
        }

        $allStopped = true;
        foreach ($running['items'] ?? [] as $egress) {
            $egressId = $egress['egress_id'] ?? $egress['egressId'] ?? null;
            if ($egressId && CallLiveKitApi::run('livekit.Egress/StopEgress', ['egress_id' => $egressId], $grant) === null) {
                $allStopped = false;
            }
        }

        return $allStopped;
    }

    public function authorize(ActionRequest $request): bool
    {
        abort_unless(StaffCallRecording::isEnabled(), 404);

        return $request->route('staffCall')->presentUsers()->where('users.id', $request->user()->id)->exists();
    }

    /**
     * @return array<string, mixed>
     */
    public function asController(StaffCall $staffCall, ActionRequest $request): array
    {
        $this->handle($staffCall, $request->user());

        return ['call' => $staffCall->fresh()->toBroadcast()];
    }
}
