<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 Feb 2024 10:36:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use App\Actions\Retina\UI\GetRetinaFirstLoadProps;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
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

        $iris_webpage = [
            'header'                => array_merge($isHeaderActive ? $headerLayout : []),
            'footer'                => array_merge($isFooterActive ? $footerLayout : []),
            'menu'                  => array_merge($isMenuActive ? $menuLayout : []),
        ];
        $iris_layout = [
            "website"               => WebsiteIrisResource::make($request->get('website'))->getArray(),
        ];

        if ($webUser->shop->type != ShopTypeEnum::FULFILMENT) {
            $iris_layout = array_merge($iris_layout, [
                'theme'                 => Arr::get($website->published_layout, 'theme'),
                'is_logged_in'          => (bool)$webUser,
                'user_auth'             => $webUser ? LoggedWebUserResource::make($webUser)->getArray() : null,
                'customer'              => $webUser?->customer,
                'variables'             => [
                    'name'                  => $webUser?->contact_name,
                    'username'              => $webUser?->username,
                    'email'                 => $webUser?->email,
                    'favourites_count'      => $webUser?->customer->favourites->count(),
                    'cart_count'        => $webUser ? $webUser->customer?->orderInBasket?->stats->number_item_transactions : null,
                    'cart_amount'       => $webUser ? $webUser->customer?->orderInBasket?->total_amount : null,
                ]
            ]);
        }

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
                "retina"    => [
                    "type" => $webUser?->shop?->type?->value,  // 'b2b', 'dropshipping', 'fulfilment'
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
