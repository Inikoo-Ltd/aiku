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
use App\Models\SysAdmin\User;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class StartStaffCallRecording
{
    use AsAction;

    /**
     * Any colleague in the call may record it; guests never. Under the call's recording lock: the row is created as
     * starting (no track is recorded for it yet), the room is marked through its metadata that every browser reads,
     * guests and late joiners included, and only then does it become recording and the speakers' egresses start. If
     * the mark cannot be set, or not one speaker could be recorded, the mark is taken off and nothing is kept.
     */
    public function handle(StaffCall $call, User $user, int $keepAudioMonths = 0): StaffCallRecording
    {
        return StaffCallRecording::lockFor($call, function () use ($call, $user, $keepAudioMonths) {
            $live = StaffCallRecording::where('staff_call_id', $call->id)->where('status', StaffCallRecording::STATUS_RECORDING)->first();
            if ($live) {
                return $live;
            }

            $recording = StaffCallRecording::create([
                'group_id'           => $call->group_id,
                'staff_call_id'      => $call->id,
                'status'             => StaffCallRecording::STATUS_STARTING,
                'started_by_user_id' => $user->id,
                'keep_audio_months'  => in_array($keepAudioMonths, StaffCallRecording::KEEP_AUDIO_MONTHS, true) ? $keepAudioMonths : 0,
                'user_ids'           => $call->presentUsers()
                    ->where('staff_call_participants.last_seen_at', '>=', now()->subSeconds(StaffCall::STALE_SECONDS))
                    ->pluck('users.id')->push($user->id)->unique()->values()->all(),
            ]);

            if (!SetStaffCallRecordingMark::run($call, $user)) {
                $this->giveUp($recording, $call);
            }

            $recording->update(['status' => StaffCallRecording::STATUS_RECORDING]);
            if (!RecordStaffCallTrack::make()->everyoneIn($recording)) {
                $this->giveUp($recording, $call);
            }

            StaffCallUpdated::dispatch($call);

            return $recording;
        });
    }

    /**
     * The mark comes off only when no egress is left in the room (one may have started although its reply timed out);
     * otherwise the row stays as failed, without telling anyone, so Stop or the sweep can take the mark off later.
     */
    private function giveUp(StaffCallRecording $recording, StaffCall $call): never
    {
        if (StopStaffCallRecording::make()->stopEveryEgressIn($call)) {
            SetStaffCallRecordingMark::run($call, null);
            $recording->delete();
        } else {
            $recording->update(['status' => StaffCallRecording::STATUS_FAILED, 'stopped_at' => now(), 'error' => 'Could not start', 'delete_audio_at' => now()->addDay()]);
        }

        throw ValidationException::withMessages(['recording' => __('Could not start recording. Try again.')]);
    }

    public function authorize(ActionRequest $request): bool
    {
        abort_unless(StaffCallRecording::isEnabled(), 404);

        $call = $request->route('staffCall');

        return $call->ended_at === null && $call->presentUsers()->where('users.id', $request->user()->id)->exists();
    }

    public function rules(): array
    {
        return ['keep_audio_months' => ['sometimes', 'integer', 'in:'.implode(',', StaffCallRecording::KEEP_AUDIO_MONTHS)]];
    }

    /**
     * @return array<string, mixed>
     */
    public function asController(StaffCall $staffCall, ActionRequest $request): array
    {
        $this->handle($staffCall, $request->user(), (int) ($request->validated('keep_audio_months') ?? 0));

        return ['call' => $staffCall->fresh()->toBroadcast()];
    }
}
