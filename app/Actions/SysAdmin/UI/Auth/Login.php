<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:23:40 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\UI\Auth;

use App\Actions\SysAdmin\User\AuthoriseUserWithLegacyPassword;
use App\Actions\SysAdmin\User\LogUserFailLogin;
use App\Actions\SysAdmin\User\LogUserLogin;
use App\Enums\SysAdmin\User\UserAuthTypeEnum;
use App\Models\SysAdmin\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class Login
{
    use AsController;

    private string $gate = 'web';

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(ActionRequest $request): RedirectResponse
    {
        $this->ensureIsNotRateLimited($request);


        $authorised = false;
        $processed  = false;
        if (config('app.with_user_legacy_passwords')) {
            $user = User::where('username', Arr::get($request->validated(), 'username'))->first();
            if ($user and $user->auth_type == UserAuthTypeEnum::AURORA) {
                $processed  = true;
                $authorised = AuthoriseUserWithLegacyPassword::run($user, $request->validated());
                if ($authorised) {
                    Auth::login($user, $request->boolean('remember'));
                }
            }
        }


        if (!$processed) {
            $authorised = Auth::guard($this->gate)->attempt(array_merge($request->validated(), ['status' => true]), $request->boolean('remember'));
        }


        if (!$authorised) {
            RateLimiter::hit($this->throttleKey($request));

            LogUserFailLogin::dispatch(
                credentials: $request->validated(),
                ip: request()->ip(),
                userAgent: $request->header('User-Agent'),
                datetime: now()
            );

            throw ValidationException::withMessages([
                'username' => trans('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));

        /** @var User $user */
        $user = auth($this->gate)->user();

        app()->instance('group', $user->group);

        LogUserLogin::dispatch(
            user: $user,
            ip: request()->ip(),
            userAgent: $request->header('User-Agent'),
            datetime: now()
        );


        $request->session()->regenerate();
        Session::put('reloadLayout', '1');


        $language = $user->language;
        if ($language) {
            app()->setLocale($language->code);
        }

        //return back();

        return redirect()->intended('dashboard');
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function asController(ActionRequest $request): RedirectResponse
    {
        $this->handle($request);
        
        // If user organisation length is 1, redirect to Organisation dashboard (Check and uncomment below if okay)
        // if (auth()->user()->authorisedOrganisations->count() === 1) {
        //     $organisation = auth()->user()->authorisedOrganisations()->first();
        //     return redirect()->intended(route('grp.org.dashboard.show', $organisation->slug));
        // }
        
        return redirect()->intended('/dashboard');
    }


    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(ActionRequest $request): void
    {
        if (!RateLimiter::tooManyAttempts($this->throttleKey($request), 5)) {
            return;
        }

        event(new Lockout($request));

        $seconds = RateLimiter::availableIn($this->throttleKey($request));

        throw ValidationException::withMessages([
            'username' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(ActionRequest $request): string
    {
        return Str::transliterate(Str::lower($request->input('username')).'|'.$request->ip());
    }

}
