<?php

/*
 * author Louis Perez
 * created on 29-12-2025-16h-54m
 * github: https://github.com/louis-perez
 * copyright 2025
*/

namespace App\Enums\UI\SupplyChain;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum MasterVariantTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE = 'showcase';
    case VARIANTS = 'variants';
    case PRODUCTS = 'products';

    public function blueprint(): array
    {
        return match ($this) {
            MasterVariantTabsEnum::SHOWCASE => [
                'title' => __('Overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
            MasterVariantTabsEnum::VARIANTS => [
                'title' => __('Variant in Shops'),
                'icon'  => 'fal fa-store',
            ],
            MasterVariantTabsEnum::PRODUCTS => [
                'title' => __('Master Products'),
                'icon'  => 'fal fa-cube',
            ],
        };
    }
}
