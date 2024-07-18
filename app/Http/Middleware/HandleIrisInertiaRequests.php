<?php
/*
 *  Author: Raul Perusquia <raul@inikoo.com>
 *  Created: Wed, 21 Sept 2022 01:57:29 Malaysia Time, Kuala Lumpur, Malaysia
 *  Copyright (c) 2022, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Middleware;
use Tightenco\Ziggy\Ziggy;

class HandleIrisInertiaRequests extends Middleware
{
    protected $rootView = 'app-iris';


    public function share(Request $request): array
    {
        $website                     = $request->get('website');
        $firstLoadOnlyProps['ziggy'] = function () use ($request) {
            return array_merge((new Ziggy())->toArray(), [
                'location' => $request->url(),
                'environment' => app()->environment(),
            ]);
        };

        return array_merge(
            $firstLoadOnlyProps,
            [
                'iris_header' => Arr::get($website->published_layout, 'header'),
                'iris_footer' => Arr::get($website->published_layout, 'footer')
            ],
            parent::share($request),
        );

    }
}
