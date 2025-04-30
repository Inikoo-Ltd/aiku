<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 Feb 2024 10:36:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use App\Actions\Retina\UI\GetRetinaFirstLoadProps;
use App\Http\Resources\UI\LoggedWebUserResource;
use App\Http\Resources\Web\WebsiteIrisResource;
use App\Models\CRM\WebUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Inertia\Middleware;
use Tightenco\Ziggy\Ziggy;
use Illuminate\Support\Arr;

class HandleRetinaInertiaRequests extends Middleware
{
    protected $rootView = 'app-retina';


    public function share(Request $request): array
    {
        /** @var WebUser $webUser */
        $webUser = $request->user();
        $firstLoadOnlyProps = [];

        if (!$request->inertia() || Session::get('reloadLayout')) {
            $firstLoadOnlyProps          = GetRetinaFirstLoadProps::run($request, $webUser);
            $firstLoadOnlyProps['ziggy'] = function () use ($request) {
                return array_merge((new Ziggy('retina'))->toArray(), [
                    'location' => $request->url(),
                ]);
            };
        }

        $website                           = $request->get('website');
        $firstLoadOnlyProps['environment'] = app()->environment();

        $headerLayout = Arr::get($website->published_layout, 'header');
        $isHeaderActive = Arr::get($headerLayout, 'status');

        $footerLayout = Arr::get($website->published_layout, 'footer');
        $isFooterActive = Arr::get($footerLayout, 'status');

        $menuLayout = Arr::get($website->published_layout, 'menu');
        $isMenuActive = Arr::get($menuLayout, 'status');

        $iris_webpage = [];
        if ($webUser?->shop?->type?->value === 'b2b' || $webUser?->shop?->type?->value === 'dropshipping') {
            $iris_webpage = [
                'header'                => array_merge($isHeaderActive == 'active' ? $headerLayout : []),
                'footer'                => array_merge($isFooterActive == 'active' ? $footerLayout : []),
                'menu'                  => array_merge($isMenuActive == 'active' ? $menuLayout : []),
            ];
        }
        $iris_layout = [
            "website"               => WebsiteIrisResource::make($request->get('website'))->getArray(),
            'theme'                 => Arr::get($website->published_layout, 'theme'),
            'is_logged_in'          => $webUser ? true : false,
            'user_auth'             => $webUser ? LoggedWebUserResource::make($webUser)->getArray() : null,
            'customer'              => $webUser ? $webUser->customer : null,
            'variables'             => [
                'name'                  => $webUser ? $webUser->contact_name : null,
                'username'              => $webUser ? $webUser->username : null,
                'email'                 => $webUser ? $webUser->email : null,
                'favourites_count'      => $webUser ? $webUser->customer->favourites->count() : null,
                'cart_count'            => 111,
                'cart_amount'           => 111,
            ]
        ];

        return array_merge(
            $firstLoadOnlyProps,
            [
                'auth'  => [
                    'user' => $webUser ? LoggedWebUserResource::make($webUser)->getArray() : null,
                    'webUser_count' => $webUser?->customer?->webUsers?->count() ?? 1,
                ],
                'flash' => [
                    'notification' => fn () => $request->session()->get('notification')
                ],
                'ziggy' => [
                    'location' => $request->url(),
                ],

                'iris' => array_merge(
                    $iris_webpage,
                    $iris_layout
                ),
            ],
            parent::share($request),
        );
    }
}
