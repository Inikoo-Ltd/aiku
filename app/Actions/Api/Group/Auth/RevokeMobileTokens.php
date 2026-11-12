<?php

/*
 * Author Louis Perez
 * Created on 12-11-2026-09h-08m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Auth;

use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateApiTokens;
use App\Actions\SysAdmin\User\Hydrators\UserHydrateApiTokens;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\Concerns\AsObject;

class RevokeMobileTokens
{
    use AsObject;

    public function handle(User $user, ?int $keepTokenId = null): void
    {
        $user->tokens()
            ->where('name', 'like', 'mobile|%')
            ->when($keepTokenId !== null, fn ($query) => $query->whereKeyNot($keepTokenId))
            ->delete();

        UserHydrateApiTokens::run($user);
        GroupHydrateApiTokens::dispatch($user->group);
    }
}
