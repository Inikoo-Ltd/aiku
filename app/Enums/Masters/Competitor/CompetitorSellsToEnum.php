<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 01 Oct 2026 12:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\Masters\Competitor;

use App\Enums\EnumHelperTrait;

enum CompetitorSellsToEnum: string
{
    use EnumHelperTrait;

    case CONSUMER  = 'consumer';
    case WHOLESALE = 'wholesale';
    case FACTORY   = 'factory';

    public static function labels(): array
    {
        return [
            'consumer'  => __('Shoppers (compare with our RRP)'),
            'wholesale' => __('Retailers (compare with our price)'),
            'factory'   => __('Factory (compare with our cost)'),
        ];
    }
}
