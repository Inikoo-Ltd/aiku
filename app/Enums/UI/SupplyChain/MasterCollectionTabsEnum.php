<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 23-06-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Enums\UI\SupplyChain;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum MasterCollectionTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;


    case SHOWCASE = 'showcase';
    case FAMILIES            = 'families';
    case PRODUCTS            = 'products';
    case COLLECTIONS         = 'collections';
    case HISTORY  = 'history';





    public function blueprint(): array
    {
        return match ($this) {

            MasterCollectionTabsEnum::HISTORY => [
                'align' => 'right',
                'type'  => 'icon',
                'title' => __('changelog'),
                'icon'  => 'fal fa-clock',

            ],
            MasterCollectionTabsEnum::FAMILIES => [
              'title' => __('Families'),
              'icon'  => 'fal fa-folder',
            ],
            MasterCollectionTabsEnum::PRODUCTS => [
                'title' => __('Products'),
                'icon'  => 'fal fa-cube',
            ],
            MasterCollectionTabsEnum::COLLECTIONS => [
                'title' => __('Collections'),
                'icon'  => 'fal fa-cube',
            ],
            MasterCollectionTabsEnum::SHOWCASE => [
                'title' => __('overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
        };
    }
}
