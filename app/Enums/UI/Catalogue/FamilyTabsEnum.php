<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 20:45:35 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Catalogue;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum FamilyTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE  = 'showcase';
    case SALES     = 'sales';

    case OFFERS    = 'offers';
    // case MAILSHOTS = 'mailshots';
    case HISTORY   = 'history';
    case IMAGES    = 'images';
    case CUSTOMERS = 'customers';

    public function blueprint(): array
    {
        return match ($this) {

            FamilyTabsEnum::SALES => [
                'title' => __('sales'),
                'icon'  => 'fal fa-money-bill-wave',
            ],
            FamilyTabsEnum::CUSTOMERS => [
                'title' => __('customers'),
                'icon'  => 'fal fa-user',
                'type'  => 'icon',
                'align' => 'right',
            ],
            FamilyTabsEnum::OFFERS => [
                'title' => __('offers'),
                'icon'  => 'fal fa-tags',
            ],
            // FamilyTabsEnum::MAILSHOTS => [
            //     'title' => __('mailshots'),
            //     'icon'  => 'fal fa-bullhorn',
            // ],
            FamilyTabsEnum::IMAGES => [
                'title' => __('media'),
                'icon'  => 'fal fa-camera-retro',
                'type'  => 'icon',
                'align' => 'right',
            ],
            FamilyTabsEnum::HISTORY => [
                'title' => __('history'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],
            FamilyTabsEnum::SHOWCASE => [
                'title' => __('overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
        };
    }
}
