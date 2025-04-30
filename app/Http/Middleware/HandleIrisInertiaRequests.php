<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Wed, 21 Sept 2022 01:57:29 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use App\Enums\Ordering\Order\OrderStateEnum;
use App\Http\Resources\UI\LoggedWebUserResource;
use App\Models\CRM\WebUser;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;
use Tightenco\Ziggy\Ziggy;

class HandleIrisInertiaRequests extends Middleware
{
    protected $rootView = 'app-iris';


    public function share(Request $request): array
    {
        /** @var WebUser $webUser */
        $webUser = Auth::guard('retina')->user();

        $website                           = $request->get('website');
        $firstLoadOnlyProps['environment'] = app()->environment();
        $firstLoadOnlyProps['ziggy']       = function () use ($request) {
            return array_merge((new Ziggy('iris'))->toArray(), [
                'location' => $request->url()
            ]);
        };
        // dd($webUser->customer->favourites->count());

        $headerLayout = Arr::get($website->published_layout, 'header');
        $isHeaderActive = Arr::get($headerLayout, 'status');

        $footerLayout = Arr::get($website->published_layout, 'footer');
        $isFooterActive = Arr::get($footerLayout, 'status');

        $menuLayout = Arr::get($website->published_layout, 'menu');
        $isMenuActive = Arr::get($menuLayout, 'status');

        return array_merge(
            $firstLoadOnlyProps,
            [
                // 'auth'  => $webUser ? LoggedWebUserResource::make($webUser)->getArray() : null,
                'auth'  => [
                    'name' => 'John Doe'
                ],
                'flash' => [
                    'notification' => fn () => $request->session()->get('notification')
                ],
                'ziggy' => [
                    'location' => $request->url(),
                ],
                "layout" =>   [
                    'app_theme' => Arr::get($website->published_layout, 'theme'),
                ],
                'iris' => [
                    'header' => array_merge(
                        $isHeaderActive == 'active' ? Arr::get($website->published_layout, 'header') : [],
                    ),
                    'footer' => array_merge(
                        $isFooterActive == 'active' ? Arr::get($website->published_layout, 'footer') : [],
                    ),
                    'menu' => array_merge(
                        $isMenuActive == 'active' ? Arr::get($website->published_layout, 'menu') : [],
                    ),
                    'theme'  => Arr::get($website->published_layout, 'theme'),
                    'is_logged_in'  => $webUser ? true : false,
                    'user_auth' => $webUser ? LoggedWebUserResource::make($webUser)->getArray() : null,
                    'customer' => $webUser ? $webUser->customer : null,
                    'variables' => [
                        'name'              => $webUser ? $webUser->contact_name : null,
                        'username'          => $webUser ? $webUser->username : null,
                        'email'             => $webUser ? $webUser->email : null,
                        'favourites_count'  => $webUser ? $webUser->customer->stats->number_favourites : null,
                        'cart_count'        => $webUser ? $webUser->customer->stats->number_orders_state_creating : null,
                        'cart_amount'       => $webUser ? $webUser->customer->orders->where('state', OrderStateEnum::CREATING)->sum('total_amount') : null,
                    ]
                ],

            ],
            parent::share($request),
        );
    }
}
