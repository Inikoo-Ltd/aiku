<?php

/*
 * author Arya Permana - Kirin
 * created on 30-06-2025-10h-38m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/


/*
 * author Arya Permana - Kirin
 * created on 20-12-2024-16h-24m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Enums\UI\CRM;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum RetinaCustomerClientsTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case ACTIVE = 'active';
    case INACTIVE = 'inactive';

    public function blueprint(): array
    {
        return match ($this) {
            RetinaCustomerClientsTabsEnum::ACTIVE => [
                'title' => __('active'),
                'icon'  => 'fal fa-user-friends',
            ],
            RetinaCustomerClientsTabsEnum::INACTIVE => [
                'title' => __('inactive'),
                'icon'  => 'fal fa-users-slash',
            ],
        };
    }
}
