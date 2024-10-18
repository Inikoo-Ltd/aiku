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

    case SHOWCASE            = 'showcase';
    case ITEMS               = 'items';
    case HISTORY             = 'history';
    case ATTACHMENTS         = 'attachments';


    public function blueprint(): array
    {
        return match ($this) {

            PurchaseOrderTabsEnum::ITEMS  => [
                'title' => __('items'),
                'icon'  => 'fal fa-bars',
            ],
            PurchaseOrderTabsEnum::SHOWCASE => [
                'title' => __('purchase orders'),
                'icon'  => 'fal fa-info-circle',
            ],
            PurchaseOrderTabsEnum::HISTORY     => [
                'title' => __('history'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],
            PurchaseOrderTabsEnum::ATTACHMENTS => [
                'align' => 'right',
                'title' => __('attachments'),
                'icon'  => 'fal fa-paperclip',
                'type'  => 'icon'
            ],
        };
    }
}
