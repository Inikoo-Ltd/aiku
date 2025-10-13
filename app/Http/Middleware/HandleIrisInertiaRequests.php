<?php

/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Wed, 21 Sept 2022 01:57:29 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dropshipping\CustomerSalesChannelStatusEnum;
use App\Http\Resources\UI\LoggedWebUserResource;
use App\Models\CRM\WebUser;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleIrisInertiaRequests extends Middleware
{
    use WithIrisInertia;

    protected $rootView = 'app-iris';


    public function share(Request $request): array
    {
        /** @var WebUser $webUser */
        $webUser = Auth::guard('retina')->user();

        $website = $request->get('website');


        $firstLoadOnlyProps = [];


        if (!$request->inertia() || Session::get('reloadLayout')) {
            $websiteTheme = Arr::get($website->published_layout, 'theme');

            $firstLoadOnlyProps = [
                'currency'    => $request->get('currency_data'),
                'environment' => app()->environment(),
                'ziggy'       => function () use ($request) {
                    return array_merge((new Ziggy('iris'))->toArray(), [
                        'location' => $request->url()
                    ]);
                },
                'iris'        => $this->getIrisData($website),
                "retina" => [
                    "type" => $request->get('shop_type'),
                ],
                "layout" => [
                    "app_theme" => Arr::get($websiteTheme, 'color'),
                ],
            ];


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
                'flash'  => [
                    'notification' => fn () => $request->session()->get('notification'),
                    'modal'        => fn () => $request->session()->get('modal')
                ],
                'ziggy'  => [
                    'location' => $request->url(),
                ],
            ],
            parent::share($request),
        );
    }
}
