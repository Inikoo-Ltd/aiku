<?php

/*
 * author Arya Permana - Kirin
 * created on 20-12-2024-16h-24m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Enums\UI\CRM;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum CustomersTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case DASHBOARD = 'dashboard';
    case CUSTOMERS = 'customers';

    public function blueprint(): array
    {
        return match ($this) {
            CustomersTabsEnum::DASHBOARD => [
                'title' => __('dashboard'),
                'icon'  => 'fal fa-tachometer-alt',
            ],
            CustomersTabsEnum::CUSTOMERS => [
                'title' => __('customers'),
                'icon'  => 'fal fa-transporter',
            ],
        };
    }
}
