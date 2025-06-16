<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 08 Apr 2024 09:52:43 Central Indonesia Time, Bali Office, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Catalogue\Product;

use App\Enums\EnumHelperTrait;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\CRM\Customer;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\Dropshipping\TiktokUser;
use App\Models\Dropshipping\WooCommerceUser;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;

enum ProductStateEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS    = 'in_process';
    case ACTIVE        = 'active';
    case DISCONTINUING = 'discontinuing';
    case DISCONTINUED  = 'discontinued';


    public static function labels($bucket = null): array
    {
        if (!$bucket || $bucket == 'all') {
            return [
                'in_process'    => __('In Process'),
                'active'        => __('Active'),
                'discontinuing' => __('Discontinuing'),
                'discontinued'  => __('Discontinued'),
            ];
        }
        if ($bucket == 'current') {
            return [
                'active'        => __('Active'),
                'discontinuing' => __('Discontinuing'),
            ];
        }

        return [];
    }

    public static function stateIcon(): array
    {
        return [
            'in_process'    => [
                'tooltip' => __('In process'),
                'icon'    => 'fal fa-seedling',
                'class'   => 'text-lime-500',  // Color for normal icon (Aiku)
                'color'   => 'lime',  // Color for box (Retina)
                'app'     => [
                    'name' => 'seedling',
                    'type' => 'font-awesome-5'
                ]
            ],
            'active'        => [
                'tooltip' => __('Active'),
                'icon'    => 'fal fa-check',
                'class'   => 'text-green-500',
                'color'   => 'green',
                'app'     => [
                    'name' => 'check',
                    'type' => 'font-awesome-5'
                ]
            ],
            'discontinuing' => [
                'tooltip' => __('Discontinuing'),
                'icon'    => 'fal fa-exclamation-triangle',
                'class'   => 'text-orange-500',
                'color'   => 'orange',
                'app'     => [
                    'name' => 'exclamation-triangle',
                    'type' => 'font-awesome-5'
                ]
            ],
            'discontinued'  => [
                'tooltip' => __('Discontinued'),
                'icon'    => 'fal fa-times',
                'class'   => 'text-red-500',
                'color'   => 'red',
                'app'     => [
                    'name' => 'times',
                ]
            ],
        ];
    }

    public static function count(Group|Shop|ProductCategory|Organisation|Collection|ShopifyUser|Customer|TiktokUser|WooCommerceUser $parent, $bucket = null): array
    {
        if ($parent instanceof ShopifyUser || $parent instanceof Customer || $parent instanceof TiktokUser || $parent instanceof WooCommerceUser) {
            return [];
        }

        $stats = $parent->stats;

        if (!$bucket || $bucket == 'all') {
            return [
                'in_process'    => $stats->number_products_state_in_process,
                'active'        => $stats->number_products_state_active,
                'discontinuing' => $stats->number_products_state_discontinuing,
                'discontinued'  => $stats->number_products_state_discontinued
            ];
        }

        if ($bucket == 'current') {
            return [
                'active'        => $stats->number_products_state_active,
                'discontinuing' => $stats->number_products_state_discontinuing,
            ];
        }

        return [];
    }


}
