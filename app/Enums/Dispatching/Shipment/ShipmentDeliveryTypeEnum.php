<?php

namespace App\Enums\Dispatching\Shipment;

use App\Enums\EnumHelperTrait;

enum ShipmentDeliveryTypeEnum: string
{
    use EnumHelperTrait;

    case HOME = 'home';
    case PICKUP_POINT = 'pickup_point';

    public static function labels(): array
    {
        return [
            self::HOME->value         => __('Home delivery'),
            self::PICKUP_POINT->value => __('Pickup point / locker'),
        ];
    }
}
