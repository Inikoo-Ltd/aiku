<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 27 Apr 2024 08:53:56 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\Fulfilment\RentalAgreement;

use App\Enums\EnumHelperTrait;

enum RentalAgreementBillingCycleEnum: string
{
    use EnumHelperTrait;

    case WEEKLY = 'weekly';
    case MONTHLY = 'monthly';

    public function labels(): array
    {
        return [
            'weekly' => __('Weekly'),
            'monthly' => __('Monthly'),
        ];
    }
}
