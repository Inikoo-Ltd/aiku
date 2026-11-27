<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 27 Nov 2026 21:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff\Meeting;

use App\Events\StaffCallUpdated;
use App\Models\Chat\StaffCall;
use App\Models\Chat\StaffMeeting;
use App\Models\SysAdmin\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShareStaffCallLink
{
    use AsAction;

    /**
     * The meeting link of a running call, made the first time someone in it asks. It ends with the call.
     */
    public function handle(StaffCall $call, User $user): StaffMeeting
    {
        $meeting = DB::transaction(function () use ($call, $user) {
            $locked = StaffCall::whereKey($call->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->ended_at !== null, 403);
            abort_if($locked->answered_at === null && $locked->staff_meeting_id === null && $locked->conversation?->type === 'dm', 409, __('Wait until the call is answered to share its link.'));

            if ($locked->staff_meeting_id) {
                return StaffMeeting::findOrFail($locked->staff_meeting_id);
            }

            $meeting = StaffMeeting::create([
                'group_id'           => $locked->group_id,
                'created_by_user_id' => $user->id,
                'for_one_call'       => true,
            ]);
            $locked->update(['staff_meeting_id' => $meeting->id]);

            return $meeting;
        });

        $call->refresh();
        StaffCallUpdated::dispatch($call);

        return $meeting;
    }

    public function authorize(ActionRequest $request): bool
    {
        abort_unless(StaffCall::isEnabled(), 404);

        /** @var StaffCall $call */
        $call = $request->route('staffCall');

        return $call->ended_at === null && $call->presentUsers()->where('users.id', $request->user()->id)->exists();
    }

    /**
     * @return array{url: string}
     */
    public function asController(StaffCall $staffCall, ActionRequest $request): array
    {
        return ['url' => $this->handle($staffCall, $request->user())->url()];
    }
}
