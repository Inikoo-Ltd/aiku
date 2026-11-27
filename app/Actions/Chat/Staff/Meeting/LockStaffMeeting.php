<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Meeting;

use App\Events\StaffCallUpdated;
use App\Models\Chat\StaffCall;
use App\Models\Chat\StaffMeetingGuest;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class LockStaffMeeting
{
    use AsAction;

    /**
     * A locked meeting takes no new guests, and anyone still waiting is turned away. Colleagues can still join.
     */
    public function handle(StaffCall $call, bool $locked): StaffCall
    {
        $meeting = $call->meeting;
        abort_unless($meeting && $call->ended_at === null, 404);

        $meeting->update(['locked_at' => $locked ? now() : null]);

        if ($locked) {
            $meeting->guests()->where('status', StaffMeetingGuest::STATUS_WAITING)->update(['status' => StaffMeetingGuest::STATUS_DENIED, 'decided_at' => now(), 'updated_at' => now()]);
        }

        $call->refresh();
        StaffCallUpdated::dispatch($call);

        return $call;
    }

    public function authorize(ActionRequest $request): bool
    {
        abort_unless(StaffCall::isEnabled(), 404);

        return DecideStaffMeetingGuest::isInCall($request->route('staffCall'), $request->user());
    }

    public function rules(): array
    {
        return ['locked' => ['required', 'boolean']];
    }

    /**
     * @return array{call: array<string, mixed>}
     */
    public function asController(StaffCall $staffCall, ActionRequest $request): array
    {
        return ['call' => $this->handle($staffCall, (bool) $request->validated('locked'))->toBroadcast()];
    }
}
