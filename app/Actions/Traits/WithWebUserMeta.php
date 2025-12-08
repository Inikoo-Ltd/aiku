<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 12 Feb 2024 12:51:58 Malaysia Time, Madrid Spain
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Models\CRM\Customer;

trait WithWebUserMeta
{
    public function getWebUserMeta(Customer $customer, $request): array
    {

        return match ($customer->stats->number_web_users) {
            0 => [
                'name' => 'add web user',
                'leftIcon' => [
                    'icon' => 'fal fa-globe',
                    'tooltip' => __('Web user'),
                ],
                'label' => __('Add web user'),
                'route' => [
                    'name' => $request->route()->getName().'.web-users.create',
                    'parameters' => $request->route()->originalParameters(),
                ],
            ],
            1 => [
                'route' => [
                    'name' => $request->route()->getName().'.web-users.show',
                    'parameters' => array_merge_recursive($request->route()->originalParameters(), ['webUser' => $customer->webUsers->first()->slug]),

                ],

                'label' => $customer->webUsers->first()->username,
                'leftIcon' => [
                    'icon' => 'fal fa-terminal',
                    'tooltip' => __('Web user'),
                ],

            ],
            default => [
                'name' => $customer->webUsers->count(),
                'leftIcon' => [
                    'icon' => 'fal fa-globe',
                    'tooltip' => __('Web users'),
                ],
            ]
        };
    }
}
