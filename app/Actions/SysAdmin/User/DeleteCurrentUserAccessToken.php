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
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class DeleteCurrentUserAccessToken extends GrpAction
{
    use AsAction;
    use WithAttributes;

    public function handle(User $user): bool
    {
        return $user->tokens()->delete();
    }

    public string $commandSignature = 'user:delete-access-token {user : user ID}';

    public function action(User $user, array $data): bool
    {
        $this->initialisation($user->group, $data);
        return $this->handle($user);
    }

    public function asController(User $user, array $data)
    {
        $this->initialisation($user->group, $data);
        return $this->handle($user);
    }
}
