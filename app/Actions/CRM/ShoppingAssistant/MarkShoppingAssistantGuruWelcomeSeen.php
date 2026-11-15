<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 15 Nov 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\ShoppingAssistant;

use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * The guru closed the welcome, so it is never shown to them again, on any device.
 */
class MarkShoppingAssistantGuruWelcomeSeen
{
    use AsAction;

    public function handle(User $user): void
    {
        $user->update(['settings' => [...($user->settings ?? []), GetShoppingAssistantTeamQuestionsForUser::WELCOME_SEEN => now()->toIso8601String()]]);
    }

    public function asController(ActionRequest $request): array
    {
        $this->handle($request->user());

        return ['seen' => true];
    }
}
