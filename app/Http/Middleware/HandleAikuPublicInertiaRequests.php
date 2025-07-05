<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 04 Feb 2024 08:46:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Tighten\Ziggy\Ziggy;

class HandleAikuPublicInertiaRequests extends Middleware
{
    protected $rootView = 'app-aiku-public';


    public function share(Request $request): array
    {
        $firstLoadOnlyProps = [
            'environment' => app()->environment(),
        ];

        $firstLoadOnlyProps['ziggy'] = function () use ($request) {
            return array_merge((new Ziggy('aiku-public'))->toArray(), [
                'location' => $request->url(),
            ]);
        };


        return array_merge(
            $firstLoadOnlyProps,
            parent::share($request),
        );
    }
}
