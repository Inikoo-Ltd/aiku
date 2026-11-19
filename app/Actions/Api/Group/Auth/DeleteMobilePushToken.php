<?php

/*
 * Author Louis Perez
 * Created on 18-11-2026-08h-47m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Auth;

use App\Models\Notifications\FcmToken;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Laravel\Sanctum\PersonalAccessToken;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class DeleteMobilePushToken
{
    use AsController;

    public function handle(PersonalAccessToken $accessToken): void
    {
        FcmToken::where('personal_access_token_id', $accessToken->id)->delete();
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user        = $request->user();
        $accessToken = $user->currentAccessToken();
        abort_unless($accessToken instanceof PersonalAccessToken, 403);

        $this->handle($accessToken);

        return response()->json(['data' => ['registered' => false]]);
    }
}
