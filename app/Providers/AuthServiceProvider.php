<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 02 Jul 2023 09:38:42 Malaysia Time, Sanur, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Providers;

use App\Models\CRM\WebUser;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\SysAdmin\User;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        Auth::viaRequest('websockets-auth', function () {
            $id = Session::get('login_web_'.sha1('Illuminate\Auth\SessionGuard'));
            if ($id !== null) {
                return User::find($id);
            }

            $id = Session::get('login_retina_'.sha1('Illuminate\Auth\SessionGuard'));

            if ($id !== null) {
                return WebUser::find($id);
            }

            $id = Session::get('login_pupil_'.sha1('Illuminate\Auth\SessionGuard'));
            if ($id !== null) {
                return ShopifyUser::find($id);
            }

            return false;
        });
    }
}
