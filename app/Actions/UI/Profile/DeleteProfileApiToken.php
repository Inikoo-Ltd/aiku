<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Jul 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\UI\Profile;

use App\Actions\OrgAction;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class DeleteProfileApiToken extends OrgAction
{
    use AsAction;

    public function handle(User $user, int $tokenId): bool
    {
        return (bool) $user->tokens()->whereKey($tokenId)->delete();
    }

    public function asController(int $tokenId, ActionRequest $request): array
    {
        $this->initialisationFromGroup(group(), $request);

        return [
            'deleted' => $this->handle($request->user(), $tokenId)
        ];
    }
}
