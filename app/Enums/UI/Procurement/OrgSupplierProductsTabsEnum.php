<?php

namespace App\Enums\UI\Procurement;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum OrgSupplierProductsTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case INDEX = 'index';
    case HISTORY = 'history';

    public function blueprint(): array
    {
        return match ($this) {
            self::INDEX => [
                'title' => __('Index'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
            self::HISTORY => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],
        };
    }
}
