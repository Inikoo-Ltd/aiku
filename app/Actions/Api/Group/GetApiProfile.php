<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 20 Jun 2025 01:08:38 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Api\Group;

use App\Actions\Api\Group\Resources\ProfileApiResource;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetApiProfile
{
    use AsAction;

    public function asController(ActionRequest $request): User
    {
        $user = $request->user();
        $user->load([
            'employees',
            'guests',
        ]);

        return $user;
    }

    public function jsonResponse(User $user): ProfileApiResource
    {
        return ProfileApiResource::make($user);
    }

}
