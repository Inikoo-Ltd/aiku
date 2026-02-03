<?php

/*
 * author Louis Perez
 * created on 20-01-2026-14h-10m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\SysAdmin\UI\Auth;

use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\Concerns\AsController;
use PragmaRX\Google2FALaravel\Support\Authenticator;

class Show2FARequired
{
    use AsController;

    public function handle(): Response|RedirectResponse
    {

        $authenticator = new Authenticator(request());

        if (!request()->user()?->is_two_factor_required || $authenticator->isActivated()) {
            return redirect()->route('grp.dashboard.show');
        }
        return Inertia::render('SysAdmin/Requires2FA', []);
    }

}
