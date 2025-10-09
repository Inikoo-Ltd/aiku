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

    // case SHOWCASE            = 'showcase';
    case TRANSACTIONS        = 'transactions';
    case HISTORY             = 'history';
    case ATTACHMENTS         = 'attachments';
    case PRODUCTS            = 'products';


    public function blueprint(): array
    {
        return match ($this) {

            PurchaseOrderTabsEnum::TRANSACTIONS  => [
                'title' => __('Transactions'),
                'icon'  => 'fal fa-bars',
            ],
            PurchaseOrderTabsEnum::PRODUCTS  => [
                'title' => __('Products'),
                'icon'  => 'fal fa-box-usd',
            ],
            // PurchaseOrderTabsEnum::SHOWCASE => [
            //     'title' => __('Purchase orders'),
            //     'icon'  => 'fal fa-info-circle',
            // ],
            PurchaseOrderTabsEnum::HISTORY     => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],
            PurchaseOrderTabsEnum::ATTACHMENTS => [
                'align' => 'right',
                'title' => __('Attachments'),
                'icon'  => 'fal fa-paperclip',
                'type'  => 'icon'
            ],
        };
    }
}
