<?php

/*
 * Author Louis Perez
 * Created on 05-11-2026-15h-36m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Auth;

use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateApiTokens;
use App\Actions\SysAdmin\User\Hydrators\UserHydrateApiTokens;
use App\Models\SysAdmin\User;
use Illuminate\Http\Response;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class LogoutMobileApp
{
    use AsController;

    public function handle(User $user): Response
    {
        $user->currentAccessToken()->delete();

        UserHydrateApiTokens::run($user);
        GroupHydrateApiTokens::dispatch($user->group);

        return response()->noContent();
    }

    public function asController(ActionRequest $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return $this->handle($user);
    }
}
