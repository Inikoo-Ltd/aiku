<?php

/*
 * author Arya Permana - Kirin
 * created on 08-07-2025-12h-30m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\UI\Profile;

use App\Actions\OrgAction;
use App\Actions\Traits\Actions\WithActionButtons;
use App\Actions\UI\WithInertia;
use App\Http\Resources\SysAdmin\MayaUserResource;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetMayaProfile extends OrgAction
{
    use AsAction;
    use WithInertia;
    use WithActionButtons;

    public function asController(ActionRequest $request): User
    {
        $this->initialisationFromGroup(group(), $request);

        return $this->handle($request->user());
    }

    public function handle(User $user): User
    {
        return $user;
    }

    public function jsonResponse(User $user): MayaUserResource
    {
        return new MayaUserResource($user);
    }
}
