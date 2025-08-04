<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 25 Mar 2023 04:02:58 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Enums\Inventory\OrgStockMovement;

use App\Enums\EnumHelperTrait;

enum OrgStockMovementTypeEnum: string
{
    use EnumHelperTrait;

    case PURCHASE           = 'purchase';
    case RETURN_DISPATCH    = 'return-dispatch';
    case RETURN_PICKED      = 'return-picked';
    case RETURN_CONSUMPTION = 'return-consumption';
    case PICKED             = 'picked';
    case LOCATION_TRANSFER  = 'location-transfer';
    case FOUND              = 'found';
    case CONSUMPTION        = 'consumption';
    case WRITE_OFF          = 'write-off';
    case ADJUSTMENT         = 'adjustment';

    case ASSOCIATE    = 'associate';
    case DISASSOCIATE = 'disassociate';
    case AUDIT = 'audit';

}
