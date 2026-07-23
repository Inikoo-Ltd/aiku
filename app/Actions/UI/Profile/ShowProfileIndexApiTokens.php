<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Profile;

use App\Actions\OrgAction;
use App\Enums\UI\SysAdmin\ProfileTabsEnum;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ShowProfileIndexApiTokens extends OrgAction
{
    use AsAction;

    public function asController(ActionRequest $request): User
    {
        $this->initialisationFromGroup(group(), $request)->withTab(ProfileTabsEnum::values());

        return $request->user();
    }

    public function jsonResponse(User $user): array
    {
        return [
            'tokens' => $user->tokens()->orderByDesc('id')->get(['id', 'name', 'last_used_at', 'created_at'])->toArray()
        ];
    }
}
