<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 21 Nov 2026 17:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff;

use App\Events\StaffConversationTyping;
use App\Models\Chat\StaffConversation;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class SendStaffTyping
{
    use AsAction;

    /**
     * Only the fact that someone is typing goes out, to the others in the conversation; nothing is stored. The browser
     * sends it at most every few seconds and the receivers forget it a few seconds after the last one.
     */
    public function handle(StaffConversation $conversation, User $typist): void
    {
        StaffConversationTyping::dispatch($conversation, $typist);
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->status && $request->route('staffConversation')->isActiveParticipant($request->user());
    }

    public function asController(StaffConversation $staffConversation, ActionRequest $request): array
    {
        $this->handle($staffConversation, $request->user());

        return ['ok' => true];
    }
}
