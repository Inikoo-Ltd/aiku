<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 10 Apr 2024 20:09:25 Central Indonesia Time, Sanur , Indonesia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\Accounting;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum OrgPaymentServiceProviderTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case SHOWCASE         = 'showcase';
    case PAYMENT_ACCOUNTS = 'payment_accounts';
    case PAYMENTS         = 'payments';
    case INVOICES         = 'invoices';
    case HISTORY          = 'history';


    public function blueprint(): array
    {
        return match ($this) {
            OrgPaymentServiceProviderTabsEnum::PAYMENT_ACCOUNTS => [
                'title' => __('Payment accounts'),
                'icon'  => 'fal fa-money-check-alt',
            ],
            OrgPaymentServiceProviderTabsEnum::PAYMENTS     => [
                'title' => __('Payments'),
                'icon'  => 'fal fa-coins',
            ],
            OrgPaymentServiceProviderTabsEnum::INVOICES     => [
                'title' => __('Invoices'),
                'icon'  => 'fal fa-file-invoice-dollar',
            ],
            OrgPaymentServiceProviderTabsEnum::HISTORY     => [
                'title'  => __('history'),
                'icon'   => 'fal fa-clock',
                'type'   => 'icon',
                'align'  => 'right',
            ],
            OrgPaymentServiceProviderTabsEnum::SHOWCASE => [
                'title' => __('Overview'),
                'icon'  => 'fal fa-tachometer-alt-fast',
            ],
        };
    }
}
