<?php

namespace App\Enums\UI\Catalogue;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum MasterDepartmentsTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case INDEX = 'index';

    public function blueprint(): array
    {
        return match ($this) {
            MasterDepartmentsTabsEnum::INDEX => [
                'title' => __('Index'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
        };
    }
}
