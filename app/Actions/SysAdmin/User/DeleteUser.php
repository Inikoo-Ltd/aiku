<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:24:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\User;

use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateUsers;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\Concerns\AsObject;

class DeleteUser
{
    use AsObject;

    public function handle(User $user): User
    {
        $user->updateQuietly([
            'username' => $user->username.'@deleted-'.$user->id,
        ]);
        $user->delete();
        GroupHydrateUsers::dispatch($user->group);

        return $user;
    }
}
