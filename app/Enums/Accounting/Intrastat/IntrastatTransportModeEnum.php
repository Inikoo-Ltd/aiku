<?php

namespace App\Enums\Accounting\Intrastat;

use App\Enums\EnumHelperTrait;

enum IntrastatTransportModeEnum: string
{
    use EnumHelperTrait;

    case SEA = '1';
    case RAIL = '2';
    case ROAD = '3';
    case AIR = '4';
    case POST = '5';
    case PIPELINE = '7';
    case INLAND_WATERWAY = '8';
    case SELF_PROPULSION = '9';

    public function label(): string
    {
        return match ($this) {
            self::SEA => 'Sea Transport',
            self::RAIL => 'Rail Transport',
            self::ROAD => 'Road Transport',
            self::AIR => 'Air Transport',
            self::POST => 'Postal Consignment',
            self::PIPELINE => 'Fixed Transport Installation',
            self::INLAND_WATERWAY => 'Inland Waterway Transport',
            self::SELF_PROPULSION => 'Own Propulsion',
        };
    }
}
