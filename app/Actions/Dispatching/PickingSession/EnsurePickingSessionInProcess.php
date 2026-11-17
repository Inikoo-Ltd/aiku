<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 17 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Dispatching\PickingSession;

use App\Enums\Dispatching\PickingSession\PickingSessionStateEnum;
use App\Models\Inventory\PickingSession;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsObject;

class EnsurePickingSessionInProcess
{
    use AsObject;

    /**
     * Starting a session, and adding or removing its delivery notes, is only done while the session
     * is still being prepared; the mobile API applies the same rule before calling these actions.
     *
     * @throws ValidationException
     */
    public function handle(PickingSession $pickingSession): void
    {
        if ($pickingSession->state !== PickingSessionStateEnum::IN_PROCESS) {
            throw ValidationException::withMessages([
                'state' => __('This can only be done while the picking session is in process. It is :state.', ['state' => $pickingSession->state->value]),
            ]);
        }
    }
}
