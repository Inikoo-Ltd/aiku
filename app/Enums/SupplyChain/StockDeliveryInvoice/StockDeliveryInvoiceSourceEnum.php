<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\SupplyChain\StockDeliveryInvoice;

use App\Enums\EnumHelperTrait;

enum StockDeliveryInvoiceSourceEnum: string
{
    use EnumHelperTrait;

    case AGENT     = 'agent';
    case ACTUAL    = 'actual';
    case ESTIMATED = 'estimated';

    public static function labels(): array
    {
        return [
            'agent'     => __('Made by the agent'),
            'actual'    => __('From the invoice received'),
            'estimated' => __('Estimated'),
        ];
    }
}
