<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 18-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\SysAdmin\User;

use App\Actions\GrpAction;
use App\Models\SysAdmin\User;
use Laravel\Sanctum\PersonalAccessToken;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class DeleteUserAccessToken extends GrpAction
{
    use AsAction;
    use WithAttributes;

    public function handle(User $user, PersonalAccessToken $token): bool
    {
        if ($token->tokenable_id == $user->id && $token->tokenable_type == class_basename($user)) {
            $token->delete();
            return true;
        }
        return false;
    }

    public function action(User $user, PersonalAccessToken $token, array $modelData): bool
    {
        $this->initialisation($user->group, $modelData);
        return $this->handle($user, $token);
    }

    public function asController(User $user, PersonalAccessToken $token, ActionRequest $request)
    {
        $this->initialisation($user->group, $request);
        $this->handle($user, $token);
    }
}
