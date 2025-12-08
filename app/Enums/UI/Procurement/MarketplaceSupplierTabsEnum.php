<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 23:13:26 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Procurement;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum MarketplaceSupplierTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE = 'showcase';
    case SUPPLIER_PRODUCTS = 'supplier_products';
    case FEEDBACKS = 'feedbacks';
    case HISTORY = 'history';
    case DATA = 'data';
    case SYSTEM_USERS = 'system_users';
    case ATTACHMENTS = 'attachments';
    case IMAGES = 'images';

    public function blueprint(): array
    {
        return match ($this) {
            MarketplaceSupplierTabsEnum::SUPPLIER_PRODUCTS => [
                'title' => __('Products'),
                'icon' => 'fal fa-box-usd',
            ],

            MarketplaceSupplierTabsEnum::SHOWCASE => [
                'title' => __('Supplier'),
                'icon' => 'fas fa-info-circle',
            ],

            MarketplaceSupplierTabsEnum::DATA => [
                'title' => __('Data'),
                'icon' => 'fal fa-database',
                'type' => 'icon',
                'align' => 'right',
            ],

            MarketplaceSupplierTabsEnum::FEEDBACKS => [
                'title' => __('Issues'),
                'icon' => 'fal fa-poop',
                'type' => 'icon',
                'align' => 'right',
            ],
            MarketplaceSupplierTabsEnum::IMAGES => [
                'title' => __('Images'),
                'icon' => 'fal fa-camera-retro',
                'type' => 'icon',
                'align' => 'right',
            ],
            MarketplaceSupplierTabsEnum::ATTACHMENTS => [
                'title' => __('Attachments'),
                'icon' => 'fal fa-paperclip',
                'type' => 'icon',
                'align' => 'right',
            ],
            MarketplaceSupplierTabsEnum::SYSTEM_USERS => [
                'title' => __('System/users'),
                'icon' => 'fal fa-paper-plane',
                'type' => 'icon',
                'align' => 'right',
            ],
            MarketplaceSupplierTabsEnum::HISTORY => [
                'title' => __('Changelog'),
                'icon' => 'fal fa-clock',
                'type' => 'icon',
                'align' => 'right',
            ],
        };
    }
}
