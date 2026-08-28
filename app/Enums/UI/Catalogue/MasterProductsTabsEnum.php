<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Enums\UI\Catalogue;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum MasterProductsTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case INDEX              = 'index';
    case PRICING            = 'pricing';
    case BULK_EDIT          = 'bulk_edit';
    case INDEX_ORDERING     = 'index_ordering';
    case SALES              = 'sales';

    public function blueprint(): array
    {
        return match ($this) {
            MasterProductsTabsEnum::INDEX => [
                'title' => __('Index'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
            MasterProductsTabsEnum::INDEX_ORDERING => [
                'title' => __('Index Ordering'),
                'icon'  => 'fal fa-sort-shapes-up-alt',
                'type'  => 'icon',
                'align' => 'right'
            ],
            MasterProductsTabsEnum::SALES => [
                'title' => __('Sales'),
                'icon'  => 'fal fa-money-bill-wave',
            ],
            MasterProductsTabsEnum::PRICING => [
                'title' => __('Pricing'),
                'icon'  => 'fal fa-money-bill',
                'type'  => 'icon',
                'align' => 'right'
            ],
            MasterProductsTabsEnum::BULK_EDIT => [
                'title' => __('Bulk edit'),
                'icon'  => 'fal fa-edit',
                'type'  => 'icon',
                'align' => 'right'
            ]
        };
    }
}
