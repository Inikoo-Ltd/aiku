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

class DeclineStaffCall
{
    use AsAction;

    /**
     * Declining a direct call ends it for the caller too. In a group call a decline only silences that person's notice,
     * which the browser does by itself, so nothing is recorded.
     */
    public function handle(StaffCall $call, User $user): StaffCall
    {
        if ($call->isDirect() && $call->answered_at === null && $user->id !== $call->started_by_user_id) {
            EndStaffCall::run($call, StaffCall::OUTCOME_DECLINED, $user);
        }

        return $call->refresh();
    }

    public function authorize(ActionRequest $request): bool
    {
        abort_unless(StaffCall::isEnabled(), 404);

        $conversation = $request->route('staffCall')->conversation;

        return $conversation !== null && StaffCall::userMayCallIn($conversation, $request->user());
    }

    /**
     * @return array<string, mixed>
     */
    public function asController(StaffCall $staffCall, ActionRequest $request): array
    {
        return ['call' => $this->handle($staffCall, $request->user())->toBroadcast()];
    }
}
