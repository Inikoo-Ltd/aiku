<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 22 Aug 2026 20:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Chat\Staff;

use App\Models\Chat\StaffConversation;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ArchiveStaffConversation
{
    use AsAction;

    public function handle(StaffConversation $conversation, User $user): void
    {
        $conversation->participants()->updateExistingPivot($user->id, ['archived_at' => now(), 'last_read_at' => now()]);
    }

    public function authorize(ActionRequest $request): bool
    {
        return $request->route('staffConversation')->hasParticipant($request->user());
    }

    public function asController(StaffConversation $staffConversation, ActionRequest $request): array
    {
        $this->handle($staffConversation, $request->user());

        return ['ok' => true];
    }
}
