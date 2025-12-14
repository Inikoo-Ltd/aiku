<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 04 Dec 2023 16:23:40 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\UI\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\RedirectResponse;
use App\Actions\CRM\Agent\UpdateAgent;
use Lorisleiva\Actions\Concerns\AsController;

class Logout
{
    use AsController;


    public function handle(Request $request): RedirectResponse
    {
        $user = Auth::user();
        if ($user) {
            UpdateAgent::make()->setOffline($user->id);
        }
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/');
    }

}
