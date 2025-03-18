<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 21 Feb 2024 22:34:23 Central Standard Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\UI\Layout;

use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Models\Dropshipping\Platform;
use App\Models\Dropshipping\ShopifyUser;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

class GetPupilDropshippingNavigation
{
    use AsAction;

    public function handle(?ShopifyUser $shopifyUser): array
    {
        $groupNavigation = [];

        if (!Arr::get($shopifyUser?->settings, 'webhooks')) {
            $groupNavigation['setup'] = [
                'label' => __('Get Started'),
                'icon' => ['fal', 'fa-tachometer-alt'],
                'root' => 'pupil.home',
                'route' => [
                    'name' => 'pupil.home'
                ],
                'topMenu' => [

                ]
            ];
        } else {
            $groupNavigation['dashboard'] = [
                'label' => __('Dashboard'),
                'icon' => ['fal', 'fa-tachometer-alt'],
                'root' => 'pupil.dashboard.show',
                'route' => [
                    'name' => 'pupil.dashboard.show'
                ],
                'topMenu' => [

                ]
            ];

            $groupNavigation = array_merge($groupNavigation, GetPupilDropshippingPlatformNavigation::run($shopifyUser, Platform::where('slug', PlatformTypeEnum::SHOPIFY->value)->first()));

            $groupNavigation['go_to_retina'] = [
                'label' => __($shopifyUser->customer?->shop?->name),
                'icon' => ['fal', 'fa-tachometer-alt'],
                'root' => 'pupil.home',
                'route' => [
                    'name' => 'pupil.home'
                ],
                'topMenu' => [

                ]
            ];
        }

        return $groupNavigation;
    }
}
