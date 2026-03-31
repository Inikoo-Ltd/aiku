<?php

/*
 * Author: Nickel
 * Created: Tue, 01 Apr 2026
 * Copyright (c) 2026, Inikoo LTD
 */

namespace App\Enums\UI\Inventory;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum OrganisationStockHistoriesTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case DAILY = 'daily';
    case WEEKLY = 'weekly';
    case MONTHLY = 'monthly';
    case YEARLY = 'yearly';

    public function blueprint(): array
    {
        return match ($this) {
            OrganisationStockHistoriesTabsEnum::DAILY => [
                'title' => __('Daily'),
                'icon'  => 'fal fa-calendar-day',
            ],
            OrganisationStockHistoriesTabsEnum::WEEKLY => [
                'title' => __('Weekly'),
                'icon'  => 'fal fa-calendar-week',
            ],
            OrganisationStockHistoriesTabsEnum::MONTHLY => [
                'title' => __('Monthly'),
                'icon'  => 'fal fa-calendar-alt',
            ],
            OrganisationStockHistoriesTabsEnum::YEARLY => [
                'title' => __('Yearly'),
                'icon'  => 'fal fa-calendar',
            ],
        };
    }
}
