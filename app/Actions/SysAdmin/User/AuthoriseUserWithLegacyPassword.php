<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 02 Jul 2023 10:15:46 Malaysia Time, Sanur, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User;

use App\Enums\SysAdmin\User\UserAuthTypeEnum;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\Concerns\AsAction;

class AuthoriseUserWithLegacyPassword
{
    use AsAction;

    public function handle(User $user, array $credentials): bool
    {
        $plain = $credentials['password'] ?? null;
        if ($plain === null) {
            return false;
        }

        if ($user->auth_type != UserAuthTypeEnum::AURORA) {
            return false;
        }

        if (! $user->status) {
            return false;
        }

        if (hash('sha256', $plain) == $user->legacy_password) {
            UpdateUser::run(
                $user,
                [
                    'password' => $plain,
                    'legacy_password' => null,
                    'auth_type' => UserAuthTypeEnum::DEFAULT,
                ]
            );

            return true;
        }

        return false;
    }
}
