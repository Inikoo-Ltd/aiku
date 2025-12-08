<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 14 Aug 2024 15:53:34 Central Indonesia Time, Bali, Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Procurement;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum OrgSupplierProductTabsEnum: string
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
            OrgSupplierProductTabsEnum::SHOWCASE => [
                'title' => __('Supplier product'),
                'icon' => 'fas fa-info-circle',
            ],
            OrgSupplierProductTabsEnum::PURCHASE_SALES => [
                'title' => __('Purchases/sales'),
                'icon' => 'fal fa-money-bill',
            ],
            OrgSupplierProductTabsEnum::SUPPLIER_PRODUCTS => [
                'title' => __('Products'),
                'icon' => 'fal fa-box-usd',
            ],

            OrgSupplierProductTabsEnum::PURCHASE_ORDERS => [
                'title' => __('Purchase orders'),
                'icon' => 'fal fa-clipboard',
            ],
            OrgSupplierProductTabsEnum::DELIVERIES => [
                'title' => __('Deliveries'),
                'icon' => 'fal fa-truck',
            ],

            OrgSupplierProductTabsEnum::FEEDBACKS => [
                'title' => __('Issues'),
                'icon' => 'fal fa-poop',
                'type' => 'icon',
                'align' => 'right',
            ],
            OrgSupplierProductTabsEnum::IMAGES => [
                'title' => __('Images'),
                'icon' => 'fal fa-camera-retro',
                'type' => 'icon',
                'align' => 'right',
            ],
            OrgSupplierProductTabsEnum::ATTACHMENTS => [
                'title' => __('Attachments'),
                'icon' => 'fal fa-paperclip',
                'type' => 'icon',
                'align' => 'right',
            ],
            OrgSupplierProductTabsEnum::HISTORY => [
                'title' => __('Changelog'),
                'icon' => 'fal fa-clock',
                'type' => 'icon',
                'align' => 'right',
            ],
        };
    }
}
