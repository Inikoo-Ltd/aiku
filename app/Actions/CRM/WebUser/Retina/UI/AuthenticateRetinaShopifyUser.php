<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Feb 2024 17:08:30 Malaysia Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\CRM\WebUser\Retina\UI;

use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Http\RedirectResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsController;

class AuthenticateRetinaShopifyUser
{
    use AsController;

    public function handle(ActionRequest $request): RedirectResponse
    {
        $retinaHome = 'app/dashboard';
        if ($request->input('shopify')) {
            $shopifyUser = ShopifyUser::where('password', base64_decode($request->input('shopify')))->first();

            if ($shopifyUser) {
                auth('retina')->login($shopifyUser->customer?->webUsers?->first());
                return redirect()->intended($retinaHome);
            }
        }

        return redirect()->intended('app/login');
    }
}
