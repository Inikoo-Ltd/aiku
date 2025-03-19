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

        if (!$request->inertia() or Session::get('reloadLayout')) {
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

                'iris' => [
                'header' => array_merge($isHeaderActive == 'active' ? Arr::get($website->published_layout, 'header') : []),
                    'footer' => array_merge($isFooterActive == 'active' ? Arr::get($website->published_layout, 'footer') : []),
                    'menu'   => Arr::get($website->published_layout, 'menu'),
                    "website" => WebsiteIrisResource::make($request->get('website'))->getArray()
                ]

            ],
            parent::share($request),
        );

    }
}
