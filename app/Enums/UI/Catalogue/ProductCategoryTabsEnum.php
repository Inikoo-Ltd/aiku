<?php

/*
 * author Arya Permana - Kirin
 * created on 19-12-2024-16h-32m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Enums\UI\Catalogue;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum ProductCategoryTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case INDEX   = 'index';
    case SALES      = 'sales';
    case NEED_REVIEW      = 'need_review';

    public function blueprint(): array
    {
        return match ($this) {
            ProductCategoryTabsEnum::INDEX => [
                'title' => __('index'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
            ProductCategoryTabsEnum::SALES => [
                'title' => __('sales'),
                'icon'  => 'fal fa-money-bill-wave',
            ],
            ProductCategoryTabsEnum::NEED_REVIEW => [
                'title' => __('need review'),
                'icon'  => 'fal fa-pen-alt',
            ],
        };
    }
}
