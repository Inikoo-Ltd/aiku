<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:23:40 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\UI\Auth;

use Illuminate\Support\Arr;
use App\Models\SysAdmin\User;
use App\Actions\Traits\WithLogin;
use Illuminate\Support\Facades\Auth;
use App\Models\SysAdmin\Organisation;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;
use App\Actions\CRM\Agent\UpdateAgent;
use Illuminate\Support\Facades\Session;
use App\Actions\SysAdmin\User\LogUserLogin;
use Illuminate\Support\Facades\RateLimiter;
use App\Enums\SysAdmin\User\UserAuthTypeEnum;
use Lorisleiva\Actions\Concerns\AsController;
use Illuminate\Validation\ValidationException;
use App\Actions\SysAdmin\User\LogUserFailLogin;
use App\Actions\SysAdmin\User\AuthoriseUserWithLegacyPassword;

class Login
{
    use AsController;
    use WithLogin;

    private string $gate = 'web';

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(ActionRequest $request): RedirectResponse
    {
        $this->ensureIsNotRateLimited($request);
        $rememberMe = $request->boolean('remember');

        $authorised = false;
        $processed  = false;
        if (config('app.with_user_legacy_passwords')) {
            $user = User::where('username', Arr::get($request->validated(), 'username'))->first();
            if ($user and $user->auth_type == UserAuthTypeEnum::AURORA) {
                $processed  = true;
                $authorised = AuthoriseUserWithLegacyPassword::run($user, $request->validated());
                if ($authorised) {
                    Auth::login($user, $rememberMe);
                }
            }
        }


        if (!$processed) {
            $authorised = Auth::guard($this->gate)->attempt(array_merge($request->validated(), ['status' => true]), $rememberMe);
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

        if ($user) {
            UpdateAgent::make()->setOnline($user->id);
        }

        $request->session()->regenerate();
        Session::put('reloadLayout', '1');


        $language = $user->language;
        if ($language) {
            app()->setLocale($language->code);
        }

        if ($user->authorisedOrganisations->count() === 1) {
            /** @var Organisation $organisation */
            $organisation = $user->authorisedOrganisations()->first();

            return redirect()->intended(route('grp.org.dashboard.show', $organisation->slug));
        }

        return redirect()->intended('/dashboard');
    }




}
