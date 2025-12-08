<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 10 Apr 2024 20:08:38 Central Indonesia Time, Sanur , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Accounting;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum PaymentServiceProviderTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE = 'showcase';
    case STATS = 'stats';
    case PAYMENT_ACCOUNTS = 'payment_accounts';
    case PAYMENTS = 'payments';
    case HISTORY = 'history';
    case DATA = 'data';

    public function blueprint(): array
    {
        return match ($this) {
            PaymentServiceProviderTabsEnum::STATS => [
                'title' => __('Stats'),
                'icon' => 'fal fa-chart-line',
            ],
            PaymentServiceProviderTabsEnum::PAYMENT_ACCOUNTS => [
                'title' => __('Payment accounts'),
                'icon' => 'fal fa-money-check-alt',
            ],
            PaymentServiceProviderTabsEnum::PAYMENTS => [
                'title' => __('Payments'),
                'icon' => 'fal fa-coins',
            ],
            PaymentServiceProviderTabsEnum::DATA => [
                'title' => __('Data'),
                'icon' => 'fal fa-database',
                'type' => 'icon',
                'align' => 'right',
            ],
            PaymentServiceProviderTabsEnum::HISTORY => [
                'title' => __('history'),
                'icon' => 'fal fa-clock',
                'type' => 'icon',
                'align' => 'right',
            ],
            PaymentServiceProviderTabsEnum::SHOWCASE => [
                'title' => __('Payment service provider'),
                'icon' => 'fas fa-info-circle',
            ],
        };
    }
}
