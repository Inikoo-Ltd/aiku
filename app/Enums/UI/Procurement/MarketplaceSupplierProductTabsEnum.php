<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 23:13:26 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Procurement;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum MarketplaceSupplierProductTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE = 'showcase';
    case FEEDBACKS = 'feedbacks';
    case HISTORY = 'history';
    case DATA = 'data';
    case ATTACHMENTS = 'attachments';
    case IMAGES = 'images';

    public function blueprint(): array
    {
        return match ($this) {
            MarketplaceSupplierProductTabsEnum::SHOWCASE => [
                'title' => __('Supplier product'),
                'icon' => 'fas fa-info-circle',
            ],

            MarketplaceSupplierProductTabsEnum::DATA => [
                'title' => __('Data'),
                'icon' => 'fal fa-database',
                'type' => 'icon',
                'align' => 'right',
            ],

            MarketplaceSupplierProductTabsEnum::FEEDBACKS => [
                'title' => __('Issues'),
                'icon' => 'fal fa-poop',
                'type' => 'icon',
                'align' => 'right',
            ],
            MarketplaceSupplierProductTabsEnum::IMAGES => [
                'title' => __('Images'),
                'icon' => 'fal fa-camera-retro',
                'type' => 'icon',
                'align' => 'right',
            ],
            MarketplaceSupplierProductTabsEnum::ATTACHMENTS => [
                'title' => __('Attachments'),
                'icon' => 'fal fa-paperclip',
                'type' => 'icon',
                'align' => 'right',
            ],
            MarketplaceSupplierProductTabsEnum::HISTORY => [
                'title' => __('Changelog'),
                'icon' => 'fal fa-clock',
                'type' => 'icon',
                'align' => 'right',
            ],
        };
    }
}
