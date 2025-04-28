<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 09 Apr 2025 13:58:03 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Enums\Dispatching\DeliveryNoteItem;

use App\Enums\EnumHelperTrait;

enum DeliveryNoteItemSalesTypeEnum: string
{
    use EnumHelperTrait;


    case B2B = 'b2b';
    case DROPSHIPPING = 'dropshipping';
    case MARKETPLACE = 'marketplace';
    case PARTNER = 'partner';
    case EMPLOYEE = 'employee';
    case VIP = 'vip';
    case NA = 'na';


    public static function labels(): array
    {
        return [
            'b2b'          => 'B2B',
            'dropshipping' => 'Dropshipping',
            'marketplace'  => 'Marketplace',
            'partner'      => 'Partner',
            'employee'     => 'Employee',
            'vip'          => 'VIP',
            'na'           => 'N/A',
        ];
    }
}
