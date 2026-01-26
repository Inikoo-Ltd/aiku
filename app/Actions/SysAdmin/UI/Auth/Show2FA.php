<?php

/*
 * author Louis Perez
 * created on 13-01-2026-15h-12m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\SysAdmin\UI\Auth;

use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;
use PragmaRX\Google2FALaravel\Support\Authenticator;

class Show2FA
{
    use AsController;

    public function handle(ActionRequest $request): Response|RedirectResponse
    {
        $authenticator = new Authenticator($request);

        if($request->user()?->is_two_factor_required && !$authenticator->isActivated()) {
            return redirect()->route('grp.login.require2fa');
        }

        if($authenticator->isAuthenticated()){
            return redirect()->route('grp.dashboard.show');
        }

        return Inertia::render('SysAdmin/Login2FA');
    }

}
