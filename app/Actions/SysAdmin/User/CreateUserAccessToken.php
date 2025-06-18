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

class CreateUserAccessToken extends GrpAction
{
    use AsAction;
    use WithAttributes;

    public function handle(User $user): string
    {
        return $user->createToken('access-token-user-'. $user->id, [])->plainTextToken;
    }

    public string $commandSignature = 'user:access-token {user : user ID} {name} {abilities*}';

    public function action(User $user, array $data): string
    {
        $this->initialisation($user->group, $data);
        return $this->handle($user);
    }

    public function asController(User $user, array $data)
    {
        $this->initialisation($user->group, $data);
        $this->handle($user);
    }
}
