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
use App\Actions\SysAdmin\UI\Auth\ValidateAndSave2FA;
use App\Actions\SysAdmin\User\AuthoriseUserWithLegacyPassword;
use App\Actions\SysAdmin\User\Hydrators\UserHydrateApiTokens;
use App\Actions\SysAdmin\User\LogUserFailLogin;
use App\Actions\SysAdmin\User\LogUserLogin;
use App\Actions\Traits\WithLogin;
use App\Actions\UI\Profile\View2FAProfile;
use App\Enums\SysAdmin\User\UserAuthTypeEnum;
use App\Models\SysAdmin\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;
use PragmaRX\Google2FAQRCode\Google2FA;

class LoginMobileApp
{
    use AsController;
    use WithLogin;

    private const string SETUP_TOKEN_PURPOSE = 'mobile-two-factor-setup';

    private const int SETUP_TOKEN_SECONDS = 600;

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

        if (config('app.mobile_two_factor')) {
            $this->ensureSecondFactorPasses(
                $user,
                Arr::get($request->validated(), 'one_time_password'),
                Arr::get($request->validated(), 'two_factor_setup_token')
            );
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
            'employees.jobPositions',
            'guests',
            'pseudoJobPositions',
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
    private function ensureSecondFactorPasses(User $user, ?string $oneTimePassword, ?string $setupToken): void
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

            $throttleKey = $this->ensureOneTimePasswordIsNotThrottled($user);

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
            $this->setUpSecondFactor($user, $oneTimePassword, $setupToken);
        }
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    private function setUpSecondFactor(User $user, ?string $oneTimePassword, ?string $setupToken): void
    {
        $secret = $this->secretFromSetupToken($user, $setupToken);
        if ($secret === null) {
            $this->requireSecondFactorSetup($user);
        }

        if ($oneTimePassword === null) {
            throw ValidationException::withMessages([
                'one_time_password' => __('Enter OTP from your Authenticator App'),
            ]);
        }

        $throttleKey = $this->ensureOneTimePasswordIsNotThrottled($user);

        try {
            ValidateAndSave2FA::make()->saveVerifiedSecret($user, $secret, $oneTimePassword);
        } catch (ValidationException $exception) {
            RateLimiter::hit($throttleKey, 900);

            throw $exception;
        }

        RateLimiter::clear($throttleKey);
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    private function ensureOneTimePasswordIsNotThrottled(User $user): string
    {
        $throttleKey = '2fa:'.$user->id;
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'one_time_password' => __('Too many wrong codes. Please try again in :minutes minutes.', [
                    'minutes' => ceil(RateLimiter::availableIn($throttleKey) / 60),
                ]),
            ]);
        }

        return $throttleKey;
    }

    private function secretFromSetupToken(User $user, ?string $setupToken): ?string
    {
        if ($setupToken === null) {
            return null;
        }

        try {
            $payload = json_decode(Crypt::decryptString($setupToken), true);
        } catch (DecryptException) {
            return null;
        }

        if (!is_array($payload)
            || Arr::get($payload, 'purpose') !== self::SETUP_TOKEN_PURPOSE
            || Arr::get($payload, 'user_id') !== $user->id
            || !is_string(Arr::get($payload, 'secret'))
            || Arr::get($payload, 'expires_at', 0) < now()->getTimestamp()) {
            return null;
        }

        return $payload['secret'];
    }

    private function requireSecondFactorSetup(User $user): never
    {
        $secret  = View2FAProfile::make()->generateSecretKey();
        $message = __('Set up two-factor authentication to continue.');

        throw new HttpResponseException(response()->json([
            'message'          => $message,
            'code'             => 'two_factor_setup_required',
            'errors'           => ['one_time_password' => [$message]],
            'two_factor_setup' => [
                'setup_token' => Crypt::encryptString(json_encode([
                    'purpose'    => self::SETUP_TOKEN_PURPOSE,
                    'user_id'    => $user->id,
                    'secret'     => $secret,
                    'expires_at' => now()->addSeconds(self::SETUP_TOKEN_SECONDS)->getTimestamp(),
                ])),
                'secret'      => $secret,
                'otpauth_url' => (new Google2FA())->getQRCodeUrl(View2FAProfile::ISSUER, $user->username, $secret),
                'issuer'      => View2FAProfile::ISSUER,
                'account'     => $user->username,
                'expires_in'  => self::SETUP_TOKEN_SECONDS,
            ],
        ], 422));
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
            'one_time_password'      => ['sometimes', 'string'],
            'two_factor_setup_token' => ['sometimes', 'string'],
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
