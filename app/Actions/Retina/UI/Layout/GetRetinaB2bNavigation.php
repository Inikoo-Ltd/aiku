<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 21 Feb 2024 22:34:23 Central Standard Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\UI\Layout;

use App\Models\CRM\WebUser;
use Lorisleiva\Actions\Concerns\AsAction;

class GetRetinaB2bNavigation
{
    use AsAction;

    public function handle(WebUser $webUser): array
    {
        $groupNavigation = [];

        $groupNavigation['dashboard'] = [
            'label' => __('Dashboard'),
            'icon' => ['fal', 'fa-tachometer-alt'],
            'root' => 'retina.dashboard.',
            'route' => [
                'name' => 'retina.dashboard.show'
            ],
            'topMenu' => [

            ]
        ];

        $groupNavigation['basket'] = [
            'label' => __('Basket'),
            'icon' => ['fal', 'fa-shopping-basket'],
            'root' => 'retina.ecom.basket.',
            'route' => [
                'name' => 'retina.ecom.basket.show'
            ],
            'topMenu' => [

            ]
        ];

        $groupNavigation['favourites'] = [
            'label' => __('Favourites'),
            'icon' => ['fal', 'fa-heart'],
            'root' => 'retina.ecom.favourites.',
            'route' => [
                'name' => 'retina.ecom.favourites.index'
            ],
            'topMenu' => [

            ]
        ];

        return $groupNavigation;
    }
}
