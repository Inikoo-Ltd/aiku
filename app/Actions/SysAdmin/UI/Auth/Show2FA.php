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
use Lorisleiva\Actions\Concerns\AsController;

class Show2FA
{
    use AsController;

    public function handle(): Response|RedirectResponse
    {
        return Inertia::render('SysAdmin/Login2FA');
    }

}
