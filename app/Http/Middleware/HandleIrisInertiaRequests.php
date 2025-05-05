<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Wed, 21 Sept 2022 01:57:29 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use App\Http\Resources\UI\LoggedWebUserResource;
use App\Models\CRM\WebUser;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Inertia\Middleware;
use Tightenco\Ziggy\Ziggy;

class HandleIrisInertiaRequests extends Middleware
{
    use WithIrisInertia;

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


        return array_merge(
            $firstLoadOnlyProps,
            [
                'auth'   => [
                    'user'          => $webUser ? LoggedWebUserResource::make($webUser)->getArray() : null,
                    'webUser_count' => $webUser?->customer?->webUsers?->count() ?? 1,
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
                'iris' => $this->getIrisData($website, $webUser)

            ],
            parent::share($request),
        );
    }
}
