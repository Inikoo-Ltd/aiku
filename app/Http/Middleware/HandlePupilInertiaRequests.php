<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Aug 2024 11:59:42 Central Indonesia Time, Bali Office, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use App\Actions\Pupil\GetPupilFirstLoadProps;
use App\Http\Resources\UI\LoggedShopifyUserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Inertia\Middleware;
use Tightenco\Ziggy\Ziggy;

class HandlePupilInertiaRequests extends Middleware
{
    protected $rootView = 'app-pupil';


    public function share(Request $request): array
    {
        /** @var \App\Models\Dropshipping\ShopifyUser $shopifyUser */
        $shopifyUser = $request->user('pupil');

        $firstLoadOnlyProps = [];

        if (!$request->inertia() or Session::get('reloadLayout')) {

            $firstLoadOnlyProps          = GetPupilFirstLoadProps::run($request, $shopifyUser);
            $firstLoadOnlyProps['ziggy'] = function () use ($request) {
                return array_merge((new Ziggy('pupil'))->toArray(), [
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
