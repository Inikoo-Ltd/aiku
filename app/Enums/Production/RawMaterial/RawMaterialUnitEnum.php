<?php

namespace App\Enums\Production\RawMaterial;

use App\Enums\EnumHelperTrait;

enum RawMaterialUnitEnum: string
{
    use EnumHelperTrait;

    case UNIT       = 'unit';
    case PACK       = 'pack';
    case CARTON     = 'carton';
    case LITER      = 'liter';
    case KILOGRAM   = 'kilogram';

    public static function labels(): array
    {
        return [
            self::UNIT->value     => 'Unit',
            self::PACK->value     => 'Pack',
            self::CARTON->value   => 'Carton',
            self::LITER->value    => 'Liter',
            self::KILOGRAM->value => 'Kilogram',
        ];
    }

}
