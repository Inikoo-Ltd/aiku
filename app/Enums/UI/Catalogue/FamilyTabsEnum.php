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

    case SHOWCASE = 'showcase';
    case CONTENT = 'content';
    case IMAGES = 'images';
    case SALES = 'sales';
    case VARIANTS = 'variants';

    case OFFERS = 'offers';
    case REVIEWS = 'reviews';
    case HISTORY = 'history';
    case CUSTOMERS = 'customers';
    case RELATED_PRODUCTS    = 'related_products';

    public function blueprint(): array
    {
        return match ($this) {
            FamilyTabsEnum::SALES => [
                'title' => __('Sales'),
                'icon'  => 'fal fa-money-bill-wave',
            ],
            FamilyTabsEnum::CUSTOMERS => [
                'title' => __('Customers'),
                'icon'  => 'fal fa-user',
                'type'  => 'icon',
                'align' => 'right',
            ],
            FamilyTabsEnum::OFFERS => [
                'title' => __('Offers'),
                'icon'  => 'fal fa-tags',
            ],
            FamilyTabsEnum::REVIEWS => [
                'title' => __('Reviews'),
                'icon'  => 'fal fa-star',
            ],
            FamilyTabsEnum::CONTENT => [
                'title' => __('Content'),
                'icon'  => 'fal fa-quote-left',
            ],
            FamilyTabsEnum::IMAGES => [
                'title' => __('Media'),
                'icon'  => 'fal fa-camera-retro',
            ],
            FamilyTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],
            FamilyTabsEnum::SHOWCASE => [
                'title' => __('Overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
            FamilyTabsEnum::VARIANTS => [
                'title' => __('Variants'),
                'icon'  => 'fal fa-shapes',
            ],
            FamilyTabsEnum::RELATED_PRODUCTS => [
                'title' => __('Related Products'),
                'icon'  => 'fal fa-repeat',
            ],
        };
    }
}
