<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 23:14:41 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Procurement;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum PurchaseOrderTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE     = 'showcase';
    case ITEMS        = 'items';
    case PRODUCTS     = 'products';
    case HISTORY      = 'history';
    // case ATTACHMENTS  = 'attachments';

    public function blueprint(): array
    {
        return match ($this) {
            PurchaseOrderTabsEnum::SHOWCASE => [
                'title' => __('Data'),
                'icon'  => 'fal fa-database',
            ],
            PurchaseOrderTabsEnum::ITEMS    => [
                'title' => __('Items'),
                'icon'  => 'fal fa-bars',
            ],
            PurchaseOrderTabsEnum::PRODUCTS => [
                'title' => __("All supplier's products"),
                'icon'  => 'fal fa-th-list',
            ],
            PurchaseOrderTabsEnum::HISTORY  => [
                'title' => __('History'),
                'type'  => 'icon',
                'icon'  => 'fal fa-clock',
                'align' => 'right',
            ],
            // PurchaseOrderTabsEnum::ATTACHMENTS => [
            //     'title' => __('Attachments'),
            //     'type'  => 'icon',
            //     'icon'  => 'fal fa-paperclip',
            //     'align' => 'right',
            // ],
        };
    }
}
