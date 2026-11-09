<?php

/*
 * Author Louis Perez
 * Created on 05-11-2026-15h-36m
 * GitHub: https://github.com/louis-perez
 * Copyright 2026
*/

namespace App\Actions\Api\Group\Auth;

use App\Actions\Api\Group\Resources\ProfileApiResource;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateApiTokens;
use App\Actions\SysAdmin\User\AuthoriseUserWithLegacyPassword;
use App\Actions\SysAdmin\User\Hydrators\UserHydrateApiTokens;
use App\Actions\SysAdmin\User\LogUserFailLogin;
use App\Actions\SysAdmin\User\LogUserLogin;
use App\Actions\Traits\WithLogin;
use App\Enums\SysAdmin\User\UserAuthTypeEnum;
use App\Models\SysAdmin\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;
use PragmaRX\Google2FAQRCode\Google2FA;

class LoginMobileApp
{
    use AsController;
    use WithLogin;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(ActionRequest $request): JsonResponse
    {
        $this->ensureIsNotRateLimited($request);

        $credentials = Arr::only($request->validated(), ['username', 'password']);
        $user        = $this->authenticatedUser($credentials);

        if (!$user) {
            RateLimiter::hit($this->throttleKey($request));

            LogUserFailLogin::dispatch(
                credentials: Arr::only($credentials, ['username']),
                ip: $request->ip(),
                userAgent: (string) $request->header('User-Agent'),
                datetime: now(),
                geoLocation: $this->geoLocation($request)
            )->delay(now()->addSeconds(5));

            throw ValidationException::withMessages([
                'username' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));

        if (!app()->isLocal()) {
            $this->ensureSecondFactorPasses($user, Arr::get($request->validated(), 'one_time_password'));
        }

        $tokenName = 'mobile|'.Arr::get($request->validated(), 'device_name');
        $user->tokens()->where('name', $tokenName)->delete();
        $newAccessToken = $user->createToken($tokenName, ['mobile']);

        UserHydrateApiTokens::run($user);
        GroupHydrateApiTokens::dispatch($user->group);

        LogUserLogin::dispatch(
            user: $user,
            ip: $request->ip(),
            userAgent: (string) $request->header('User-Agent'),
            datetime: now(),
            geoLocation: $this->geoLocation($request)
        )->delay(now()->addSeconds(5));

        $user->load([
            'employees',
            'guests',
        ]);

        return response()->json([
            'token'      => $newAccessToken->plainTextToken,
            'expires_at' => $newAccessToken->accessToken->expires_at?->toIso8601ZuluString(),
            'user'       => ProfileApiResource::make($user),
        ]);
    }

    private function authenticatedUser(array $credentials): ?User
    {
        if (config('app.with_user_legacy_passwords')) {
            $user = User::where('username', $credentials['username'])->first();
            if ($user && $user->auth_type == UserAuthTypeEnum::AURORA) {
                return AuthoriseUserWithLegacyPassword::run($user, $credentials) ? $user : null;
            }
        }

        $guard = Auth::guard('web');
        if (!$guard->validate(array_merge($credentials, ['status' => true]))) {
            return null;
        }

        /** @var User $user */
        $user = $guard->getLastAttempted();

        return $user;
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    private function ensureSecondFactorPasses(User $user, ?string $oneTimePassword): void
    {
        if ($user->google2fa_secret) {
            if ($oneTimePassword === null) {
                $message = __('Enter OTP from your Authenticator App');

                throw new HttpResponseException(response()->json([
                    'message' => $message,
                    'code'    => 'otp_required',
                    'errors'  => ['one_time_password' => [$message]],
                ], 422));
            }

            $throttleKey = '2fa:'.$user->id;
            if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
                throw ValidationException::withMessages([
                    'one_time_password' => __('Too many wrong codes. Please try again in :minutes minutes.', [
                        'minutes' => ceil(RateLimiter::availableIn($throttleKey) / 60),
                    ]),
                ]);
            }

            if (!(new Google2FA())->verifyKey($user->google2fa_secret, $oneTimePassword)) {
                RateLimiter::hit($throttleKey, 900);

                throw ValidationException::withMessages([
                    'one_time_password' => trans('Invalid OTP is given. Please check your Authenticator App'),
                ]);
            }

            RateLimiter::clear($throttleKey);

            return;
        }

        if ($user->is_two_factor_required) {
            throw ValidationException::withMessages([
                'username' => __('Set up two-factor authentication on the web first.'),
            ]);
        }
    }

    private function geoLocation(ActionRequest $request): array
    {
        return [
            $request->header('CF-IPCountry') ?? 'XX',
            $request->header('CF-Region'),
            $request->header('CF-IPCity'),
            $request->header('CF-IPLongitude'),
            $request->header('CF-IPLatitude'),
        ];
    }

    public function rules(): array
    {
        return [
            'username'          => ['required', 'string'],
            'password'          => ['required', 'string'],
            'device_name'       => ['required', 'string', 'max:100'],
            'one_time_password' => ['sometimes', 'string'],
        ];
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function asController(ActionRequest $request): JsonResponse
    {
        return $this->handle($request);
    }
}
