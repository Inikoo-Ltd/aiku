<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Thu, 11 Aug 2022 18:11:19 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Inikoo
 *  Version 4.0
 */

namespace App\Http\Middleware;

use App\Actions\UI\Grp\GetFirstLoadProps;
use App\Http\Resources\UI\LoggedUserResource;
use App\Models\SysAdmin\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Inertia\Middleware;
use Tightenco\Ziggy\Ziggy;

class HandleInertiaGrpRequests extends Middleware
{
    protected $rootView = 'app-grp';

    public function share(Request $request): array
    {


        $routeName = $request->route()->getName();
        if (str_starts_with($routeName, 'grp.json.')) {
            return [];
        }


        /** @var User $user */
        $user = $request->user();

        $firstLoadOnlyProps = [];


        if (!$request->inertia() || Session::get('reloadLayout')) {

            $firstLoadOnlyProps          = GetFirstLoadProps::run($user);
            $firstLoadOnlyProps['ziggy'] = function () use ($request) {
                return array_merge((new Ziggy('grp'))->toArray(), [
                    'location' => $request->url(),
                ]);
            };
            if (Session::get('reloadLayout') == 'remove') {
                Session::forget('reloadLayout');
            }
            if (Session::get('reloadLayout')) {
                Session::put('reloadLayout', 'remove');
            }
        }


        return array_merge(
            $firstLoadOnlyProps,
            [
                'auth'  => [
                    'user' => $request->user() ? LoggedUserResource::make($request->user())->getArray() : null,
                ],
                'flash' => [
                    'notification' => fn () => $request->session()->get('notification'),
                    'modal'        => fn () => $request->session()->get('modal')
                ],
                'ziggy' => [
                    'location' => $request->url(),
                ],

            ],
            parent::share($request),
        );
    }
}
