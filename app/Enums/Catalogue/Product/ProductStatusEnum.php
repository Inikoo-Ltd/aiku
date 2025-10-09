<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 08 Apr 2024 09:52:43 Central Indonesia Time, Bali Office , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Catalogue\Product;

use App\Enums\EnumHelperTrait;
use App\Models\Catalogue\Collection;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Models\Dropshipping\ShopifyUser;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;

enum ProductStatusEnum: string
{
    use EnumHelperTrait;

    case IN_PROCESS = 'in_process';
    case FOR_SALE = 'for-sale';
    case NOT_FOR_SALE = 'not-for-sale';
    case OUT_OF_STOCK = 'out-of-stock';
    case DISCONTINUED = 'discontinued';


    public static function labels(): array
    {
        return [
            'in_process'   => __('In Process'),
            'for-sale'     => __('For Sale'),
            'not-for-sale' => __('Not For Sale'),
            'out-of-stock' => __('Out of Stock'),
            'discontinued' => __('Discontinued'),
        ];
    }

    public static function stateIcon(): array
    {
        return [
            'in_process'   => [
                'tooltip' => __('In process'),
                'icon'    => 'fal fa-seedling',
                'class'   => 'text-lime-500',  // Color for normal icon (Aiku)
                'color'   => 'lime',  // Color for box (Retina)
                'app'     => [
                    'name' => 'seedling',
                    'type' => 'font-awesome-5'
                ]
            ],
            'for-sale'     => [
                'tooltip' => __('For Sale'),
                'icon'    => 'fal fa-check',
                'class'   => 'text-emerald-500',
                'color'   => 'emerald',
                'app'     => [
                    'name' => 'check',
                    'type' => 'font-awesome-5'
                ]
            ],
            'not-for-sale' => [
                'tooltip' => __('Not For Sale'),
                'icon'    => 'fal fa-ban',
                'class'   => 'text-gray-500',
                'color'   => 'gray',
                'app'     => [
                    'name' => 'ban',
                    'type' => 'font-awesome-5'
                ]
            ],
            'out-of-stock' => [
                'tooltip' => __('Out of Stock'),
                'icon'    => 'fal fa-exclamation-triangle',
                'class'   => 'text-orange-500',
                'color'   => 'orange',
                'app'     => [
                    'name' => 'exclamation-triangle',
                    'type' => 'font-awesome-5'
                ]
            ],
            'discontinued' => [
                'tooltip' => __('Discontinued'),
                'icon'    => 'fal fa-times',
                'class'   => 'text-red-500',
                'color'   => 'red',
                'app'     => [
                    'name' => 'times',
                    'type' => 'font-awesome-5'
                ]
            ]
        ];
    }

    public static function count(Group|Shop|ProductCategory|Organisation|Collection|ShopifyUser $parent): array
    {
        if ($parent instanceof ShopifyUser) {
            return [];
        }

        $stats = $parent->stats;

        return [
            'in_process'   => $stats->number_products_state_in_process,
            'for-sale'     => $stats->number_products_state_for_sale,
            'not-for-sale' => $stats->number_products_state_not_for_sale,
            'out-of-stock' => $stats->number_products_state_out_of_stock,
            'discontinued' => $stats->number_products_state_discontinued

        ];
    }


}
