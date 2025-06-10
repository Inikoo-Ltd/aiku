<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 18 Jun 2024 13:08:11 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Helpers\Snapshot;

use App\Enums\EnumHelperTrait;

enum SnapshotScopeEnum: string
{
    use EnumHelperTrait;

    case WEBPAGE = 'webpage';
    case HEADER = 'header';
    case FOOTER = 'footer';
    case MENU = 'menu';
    case THEME = 'theme';
    case BANNER = 'banner';
    case EMAIL = 'email';
    case PRODUCT_TEMPLATE = 'product_template'; // for website web block
    case DEPARTMENT = 'department';
    case FAMILY = 'family';
    case SUB_DEPARTMENT = 'sub_department';
    case COLLECTION = 'collection';
    case PRODUCT = 'product';
    case PRODUCTS = 'products';

    public static function labels(): array
    {
        return [
            'webpage'          => __('Webpage'),
            'header'           => __('Header'),
            'footer'           => __('Footer'),
            'menu'             => __('Menu'),
            'theme'            => __('Theme'),
            'banner'           => __('Banner'),
            'email'            => __('Email'),
            'product_template' => __('Product template'),
            'department'       => __('Department'),
            'family'           => __('Family'),
            'sub_department'   => __('Sub Department'),
            'collection'       => __('Collection'),
            'product'          => __('Product'),
            'products'          => __('Products'),
        ];
    }
}
