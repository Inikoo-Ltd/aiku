<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 21:30:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Meeting;

use App\Actions\Chat\Staff\Call\EndStaffCall;
use App\Models\Chat\StaffCall;
use App\Models\Chat\StaffMeeting;
use App\Models\Chat\StaffMeetingGuest;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class EndStaffMeeting
{
    use AsAction;

    /**
     * Whoever made the link ends it for good: the call running on it ends, and the link only shows the ended page.
     */
    public function handle(StaffMeeting $meeting): StaffMeeting
    {
        if ($meeting->ended_at === null) {
            $meeting->update(['ended_at' => now()]);
            $meeting->guests()->where('status', StaffMeetingGuest::STATUS_WAITING)->update(['status' => StaffMeetingGuest::STATUS_LEFT, 'left_at' => now(), 'updated_at' => now()]);
        }

        $live = $meeting->liveCall()->first();
        if ($live) {
            EndStaffCall::run($live);
        }

        return $meeting->refresh();
    }

    public function authorize(ActionRequest $request): bool
    {
        abort_unless(StaffCall::isEnabled(), 404);

        return $request->route('staffMeeting')->created_by_user_id === $request->user()->id;
    }

    /**
     * @return array{ended: bool}
     */
    public function asController(StaffMeeting $staffMeeting, ActionRequest $request): array
    {
        return ['ended' => $this->handle($staffMeeting)->ended_at !== null];
    }
}
