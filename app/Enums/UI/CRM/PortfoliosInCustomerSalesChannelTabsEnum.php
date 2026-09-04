<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\UI\CRM;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum PortfoliosInCustomerSalesChannelTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case PORTFOLIOS = 'portfolios';
    case LOGS       = 'logs';

    public function blueprint(): array
    {
        return match ($this) {
            PortfoliosInCustomerSalesChannelTabsEnum::PORTFOLIOS => [
                'title' => __('Portfolios'),
                'icon'  => 'fal fa-bookmark',
            ],
            PortfoliosInCustomerSalesChannelTabsEnum::LOGS => [
                'title' => __('Logs'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],
        };
    }
}
