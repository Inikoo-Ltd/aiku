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

    public function label(): string
    {
        return match ($this) {
            self::PURCHASE              => __('Purchase'),
            self::RETURN_DISPATCH       => __('Return Dispatch'),
            self::RETURN_PICKED         => __('Return Picked'),
            self::RETURN_CONSUMPTION    => __('Return Consumption'),
            self::PICKED                => __('Picked'),
            self::LOCATION_TRANSFER     => __('Location Transfer'),
            self::FOUND                 => __('Found'),
            self::CONSUMPTION           => __('Consumption'),
            self::WRITE_OFF             => __('Write Off'),
            self::ADJUSTMENT            => __('Adjustment'),
            self::ASSOCIATE             => __('Associate'),
            self::DISASSOCIATE          => __('Disassociate'),
            self::AUDIT                 => __('Audit'),
        };
    }

}
