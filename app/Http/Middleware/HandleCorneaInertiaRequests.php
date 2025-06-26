<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 07 Feb 2025 19:51:49 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use App\Actions\Cornea\UI\GetCorneaFirstLoadProps;
use App\Http\Resources\UI\LoggedShopifyUserResource;
use App\Models\SupplyChain\SupplierUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Inertia\Middleware;
use Tightenco\Ziggy\Ziggy;

class HandleCorneaInertiaRequests extends Middleware
{
    protected $rootView = 'app-cornea';


    public function share(Request $request): array
    {
        /** @var SupplierUser $supplierUser */
        $supplierUser = $request->user();

        $firstLoadOnlyProps = [];

        if (!$request->inertia() || Session::get('reloadLayout')) {

            $firstLoadOnlyProps          = GetCorneaFirstLoadProps::run($request, $supplierUser);
            $firstLoadOnlyProps['ziggy'] = function () use ($request) {
                return array_merge((new Ziggy('cornea'))->toArray(), [
                    'location' => $request->url(),
                ]);
            };
        }

        return array_merge(
            $firstLoadOnlyProps,
            [
                'auth'  => [
                    'user' => $request->user() ? LoggedShopifyUserResource::make($request->user())->getArray() : null,
                ],
                'flash' => [
                    'notification' => fn () => $request->session()->get('notification')
                ],
                'ziggy' => [
                    'location' => $request->url(),
                ],
            ],
            parent::share($request),
        );

    }
}
