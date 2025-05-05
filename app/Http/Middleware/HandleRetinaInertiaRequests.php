<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 Feb 2024 10:36:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use App\Actions\Retina\UI\GetRetinaFirstLoadProps;
use App\Http\Resources\UI\LoggedWebUserResource;
use App\Models\CRM\WebUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;
use Inertia\Middleware;
use Tightenco\Ziggy\Ziggy;

class HandleRetinaInertiaRequests extends Middleware
{
    use WithIrisInertia;

    protected $rootView = 'app-retina';


    public function share(Request $request): array
    {
        /** @var WebUser $webUser */
        $webUser            = $request->user();
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


        return array_merge(
            $firstLoadOnlyProps,
            [
                'auth'   => [
                    'user'          => $webUser ? LoggedWebUserResource::make($webUser)->getArray() : null,
                    'webUser_count' => $webUser?->customer?->webUsers?->count() ?? 1,
                ],
                'flash'  => [
                    'notification' => fn () => $request->session()->get('notification')
                ],
                'ziggy'  => [
                    'location' => $request->url(),
                ],
                "retina" => [
                    "type" => $webUser?->shop?->type?->value,
                ],
                'iris'   => $this->getIrisData($website, $webUser)
            ],
            parent::share($request),
        );
    }
}
