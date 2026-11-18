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
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\PersonalAccessToken;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class UpdateMobilePushToken
{
    use AsController;

    /**
     * @param array{token: string, platform: string, app_version?: string|null} $modelData
     */
    public function handle(User $user, PersonalAccessToken $accessToken, array $modelData): FcmToken
    {
        return DB::transaction(function () use ($user, $accessToken, $modelData) {
            FcmToken::where('fcm_token', $modelData['token'])
                ->where('personal_access_token_id', '!=', $accessToken->id)
                ->delete();

            return FcmToken::updateOrCreate(
                ['personal_access_token_id' => $accessToken->id],
                [
                    'push_notifiable_type' => $user->getMorphClass(),
                    'push_notifiable_id'   => $user->id,
                    'fcm_token'            => $modelData['token'],
                    'platform'             => $modelData['platform'],
                    'app_version'          => Arr::get($modelData, 'app_version'),
                ]
            );
        });
    }

    public function rules(): array
    {
        return [
            'token'       => ['required', 'string', 'max:4096'],
            'platform'    => ['required', 'string', Rule::in(['android', 'ios'])],
            'app_version' => ['sometimes', 'nullable', 'string', 'max:32'],
        ];
    }

    public function asController(ActionRequest $request): JsonResponse
    {
        /** @var User $user */
        $user        = $request->user();
        $accessToken = $user->currentAccessToken();
        abort_unless($accessToken instanceof PersonalAccessToken, 403);

        $this->handle($user, $accessToken, $request->validated());

        return response()->json(['data' => ['registered' => true]]);
    }
}
