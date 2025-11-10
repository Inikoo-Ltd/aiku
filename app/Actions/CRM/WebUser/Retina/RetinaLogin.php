<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:23:40 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\WebUser\Retina;

use App\Actions\CRM\WebUser\AuthoriseWebUserWithLegacyPassword;
use App\Actions\CRM\WebUser\LogWebUserLogin;
use App\Actions\SysAdmin\User\LogUserFailLogin;
use App\Actions\Traits\WithLogin;
use App\Actions\Web\Webpage\Iris\ShowIrisWebpage;
use App\Enums\CRM\WebUser\WebUserAuthTypeEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\CRM\WebUser;
use App\Models\Web\Webpage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class RetinaLogin
{
    use AsController;
    use WithLogin;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(ActionRequest $request): array | RedirectResponse
    {
        $this->ensureIsNotRateLimited($request);

        $websiteId  = $request->get('website')->id;
        $rememberMe = $request->boolean('remember');

        $authorised = false;
        $processed  = false;
        if (config('app.with_user_legacy_passwords')) {
            $handle = Arr::get($request->validated(), 'username');


            $webUser = WebUser::where('website_id', $websiteId)
                ->where('status', true)
                ->where('username', $handle)->first();
            if (!$webUser) {
                $webUser = WebUser::where('website_id', $websiteId)
                    ->where('status', true)
                    ->where('email', $handle)->first();
            }

            if ($webUser && $webUser->auth_type == WebUserAuthTypeEnum::AURORA) {
                $processed  = true;
                $authorised = AuthoriseWebUserWithLegacyPassword::run($webUser, $request->validated());
                if ($authorised) {
                    Auth::guard('retina')->login($webUser, $rememberMe);
                }
            }
        }

        if (!$processed) {
            $credentials = array_merge(
                $request->validated(),
                [
                    'website_id' => $websiteId,
                    'status'     => true
                ]
            );

            $authorised = Auth::guard('retina')->attempt($credentials, $rememberMe);


            if (!$authorised) {
                // try now with email
                data_set($credentials, 'email', $credentials['username']);
                data_forget($credentials, 'username');

                $authorised = Auth::guard('retina')->attempt($credentials, $rememberMe);
            }
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
                'username' => __('The provided credentials do not match our records.'),
            ]);
        }

        RateLimiter::clear($this->throttleKey($request));

        return $this->postProcessRetinaLogin($request);
    }


    public function postProcessRetinaLogin($request): array | RedirectResponse
    {
        RateLimiter::clear($this->throttleKey($request));

        /** @var WebUser $webUser */
        $webUser = auth('retina')->user();

        LogWebUserLogin::dispatch(
            webUser: $webUser,
            ip: request()->ip(),
            userAgent: $request->header('User-Agent'),
            datetime: now()
        );


        $request->session()->regenerate();
        Session::put('reloadLayout', '1');
        Cookie::queue('iris_vua', true, config('session.lifetime') * 60);



        $language = $webUser->language;
        if ($language) {
            app()->setLocale($language->code);
        }

        $retinaHome = '';
        $webpage_key = request()->get('ref');
        if ($webpage_key && is_numeric($webpage_key)) {
            $webpage = Webpage::where('id', $webpage_key)->where('website_id', $request->get('website')->id)
                ->where('state', WebpageStateEnum::LIVE)->first();
            if ($webpage) {
                $retinaHome = ShowIrisWebpage::make()->getEnvironmentUrl($webpage->canonical_url);
            }
        }

        return [$retinaHome];

    }

}
