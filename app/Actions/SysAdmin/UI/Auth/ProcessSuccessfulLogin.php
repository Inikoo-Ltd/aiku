<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 20 Jul 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\UI\Auth;

use App\Actions\Chat\Agent\UpdateAgent;
use App\Actions\SysAdmin\User\LogUserLogin;
use App\Enums\SysAdmin\Organisation\OrganisationTypeEnum;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Inertia\Inertia;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse;
use Lorisleiva\Actions\Concerns\AsObject;
use PragmaRX\Google2FALaravel\Support\Authenticator;
use Symfony\Component\HttpFoundation\Response;

class ProcessSuccessfulLogin implements PasskeyLoginResponse
{
    use AsObject;

    /**
     * The staff login is an Inertia form, and the page that connects an AI assistant is plain
     * HTML. Following that redirect inside Inertia would open the page in an error modal, so
     * the browser is sent there with a full page load.
     */
    public static function leaveInertiaForOauth(RedirectResponse $redirect): Response
    {
        if (parse_url($redirect->getTargetUrl(), PHP_URL_PATH) === '/oauth/authorize') {
            return Inertia::location($redirect->getTargetUrl());
        }

        return $redirect;
    }

    public function handle(User $user, Request $request): RedirectResponse
    {
        app()->instance('group', $user->group);

        $geoLocation = [
            $request->header('CF-IPCountry') ?? 'XX',
            $request->header('CF-Region'),
            $request->header('CF-IPCity'),
            $request->header('CF-IPLongitude'),
            $request->header('CF-IPLatitude'),
        ];

        LogUserLogin::dispatch(
            user: $user,
            ip: $request->ip(),
            userAgent: $request->header('User-Agent'),
            datetime: now(),
            geoLocation: $geoLocation
        )->delay(now()->addSeconds(5));

        UpdateAgent::make()->setOnline($user->id);

        $request->session()->regenerate();
        Session::put('reloadLayout', '1');

        $language = $user->language;
        if ($language) {
            app()->setLocale($language->code);
        }
        \Sentry\traceMetrics()->count('aiku.login.ok', 1, ['user' => $user->slug]);

        if ($user->authorisedOrganisations->count() === 1) {
            /** @var Organisation $organisation */
            $organisation = $user->authorisedOrganisations()->first();

            if ($organisation->type === OrganisationTypeEnum::AGENT && $user->authTo("procurement.$organisation->id.view")) {
                return redirect()->intended(route('grp.org.agent.purchase_orders.overview', $organisation->slug));
            }

            return redirect()->intended(route('grp.org.dashboard.show', $organisation->slug));
        }

        return redirect()->intended('/dashboard');
    }

    public function toResponse($request): JsonResponse|RedirectResponse
    {
        /** @var User $user */
        $user = Auth::guard('web')->user();

        $redirect = $this->handle($user, $request);

        (new Authenticator($request))->login();

        if ($request->wantsJson()) {
            return new JsonResponse([
                'redirect' => $redirect->getTargetUrl(),
            ]);
        }

        return $redirect;
    }
}
