<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 23:17:08 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\SupplyChain;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum SupplierProductTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE = 'showcase';
    case PURCHASE_SALES = 'purchase_sales';
    case SUPPLIER_PRODUCTS = 'supplier_products';
    case PURCHASE_ORDERS = 'purchase_orders';
    case DELIVERIES = 'deliveries';
    case FEEDBACKS = 'feedbacks';
    case HISTORY = 'history';
    case ATTACHMENTS = 'attachments';
    case IMAGES = 'images';


    public function blueprint(): array
    {
        return match ($this) {
            SupplierProductTabsEnum::SHOWCASE => [
                'title' => __('Supplier product'),
                'icon'  => 'fas fa-info-circle',
            ],
            SupplierProductTabsEnum::PURCHASE_SALES => [
                'title' => __('Purchases/sales'),
                'icon'  => 'fal fa-money-bill',
            ],
            SupplierProductTabsEnum::SUPPLIER_PRODUCTS => [
                'title' => __('Products'),
                'icon'  => 'fal fa-box-usd',
            ],

            SupplierProductTabsEnum::PURCHASE_ORDERS => [
                'title' => __('Purchase orders'),
                'icon'  => 'fal fa-clipboard',
            ],
            SupplierProductTabsEnum::DELIVERIES => [
                'title' => __('Deliveries'),
                'icon'  => 'fal fa-truck',
            ],

            SupplierProductTabsEnum::FEEDBACKS => [
                'title' => __('Issues'),
                'icon'  => 'fal fa-poop',
                'type'  => 'icon',
                'align' => 'right',
            ],
            SupplierProductTabsEnum::IMAGES => [
                'title' => __('Images'),
                'icon'  => 'fal fa-camera-retro',
                'type'  => 'icon',
                'align' => 'right',
            ],
            SupplierProductTabsEnum::ATTACHMENTS => [
                'title' => __('Attachments'),
                'icon'  => 'fal fa-paperclip',
                'type'  => 'icon',
                'align' => 'right',
            ],
            SupplierProductTabsEnum::HISTORY => [
                'title' => __('Changelog'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right'
            ],
        };
    }
}
