<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 23:17:08 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\SupplyChain;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum SupplierTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE = 'showcase';
    case HISTORY  = 'history';

    // case PURCHASES_SALES = 'purchase_sales';
    // case ATTACHMENTS     = 'attachments';
    // case SYSTEM_USERS    = 'system_users';
    // case IMAGES          = 'images';
    // case FEEDBACKS       = 'feedbacks';

    public function blueprint(): array
    {
        return match ($this) {
            SupplierTabsEnum::SHOWCASE => [
                'title' => __('Supplier'),
                'icon'  => 'fas fa-info-circle',
            ],
            SupplierTabsEnum::HISTORY => [
                'title' => __('Changelog'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],

            // SupplierTabsEnum::PURCHASES_SALES => [
            //     'title' => __('Purchases/Sales'),
            //     'icon'  => 'fal fa-money-bill',
            // ],
            // SupplierTabsEnum::ATTACHMENTS => [
            //     'title' => __('Attachments'),
            //     'icon'  => 'fal fa-paperclip',
            //     'type'  => 'icon',
            //     'align' => 'right',
            // ],
            // SupplierTabsEnum::SYSTEM_USERS => [
            //     'title' => __('System Users'),
            //     'icon'  => 'fal fa-paper-plane',
            //     'type'  => 'icon',
            //     'align' => 'right',
            // ],
            // SupplierTabsEnum::IMAGES => [
            //     'title' => __('Images'),
            //     'icon'  => 'fal fa-camera-retro',
            //     'type'  => 'icon',
            //     'align' => 'right',
            // ],
            // SupplierTabsEnum::FEEDBACKS => [
            //     'title' => __('Issues'),
            //     'icon'  => 'fal fa-poop',
            //     'type'  => 'icon',
            //     'align' => 'right',
            // ],
        };
    }
}
