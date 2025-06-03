<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Feb 2024 02:20:53 Mex Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\CRM\Customer\CustomerStatusEnum;
use Illuminate\Auth\Middleware\Authenticate as Middleware;

class RetinaAuthenticate extends Middleware
{
    public function handle($request, \Closure $next, ...$guards)
    {

        $this->authenticate($request, $guards);

        // handle pre registration for retina but not for fulfilment or logout
        $webUser = request()->user('retina');

        if (!$webUser) {
            return $next($request);
        }

        $customer = $webUser->customer;
        $shop = $webUser->shop;

        $redirectRoute = 'retina.finish_pre_register';

        if ($shop->type == ShopTypeEnum::FULFILMENT || $request->route()->getName() == 'retina.logout') {
            return $next($request);
        }


        if ($customer &&
        $customer->status == CustomerStatusEnum::PRE_REGISTRATION &&
        $request->route()->getName() !== $redirectRoute) {
            return redirect()->route($redirectRoute);
        }

        return $next($request);
    }
    protected function redirectTo($request): ?string
    {
        if (!$request->expectsJson()) {
            return route('retina.login.show');
        }
        return null;
    }
}
