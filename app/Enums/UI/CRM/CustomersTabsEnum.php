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

    case CUSTOMERS = 'customers';
    case DASHBOARD = 'dashboard';

    public function blueprint(): array
    {
        return match ($this) {
            CustomersTabsEnum::DASHBOARD => [
                'title' => __('Dashboard'),
                'icon'  => 'fal fa-tachometer-alt',
            ],
            CustomersTabsEnum::CUSTOMERS => [
                'title' => __('Customers'),
                'icon'  => 'fal fa-transporter',
            ],
        };
    }
}
