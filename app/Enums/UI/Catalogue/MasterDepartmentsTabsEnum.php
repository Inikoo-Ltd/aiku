<?php

namespace App\Enums\UI\Catalogue;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum MasterDepartmentsTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case INDEX = 'index';
    case SALES = 'sales';

    public function blueprint(): array
    {
        return match ($this) {
            MasterDepartmentsTabsEnum::INDEX => [
                'title' => __('Index'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
            MasterDepartmentsTabsEnum::SALES => [
                'title' => __('Sales'),
                'icon'  => 'fal fa-money-bill-wave',
            ],
        };
    }
}
