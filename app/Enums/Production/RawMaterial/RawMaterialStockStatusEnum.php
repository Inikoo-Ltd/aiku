<?php

namespace App\Enums\Production\RawMaterial;

use App\Enums\EnumHelperTrait;

enum RawMaterialStockStatusEnum: string
{
    use EnumHelperTrait;

    case UNLIMITED      = 'unlimited';
    case SURPLUS        = 'surplus';
    case OPTIMAL        = 'optimal';
    case LOW            = 'low';
    case CRITICAL       = 'critical';
    case OUT_OF_STOCK   = 'out_of_stock';
    case ERROR          = 'error';

    public static function labels(): array
    {
        return [
            self::UNLIMITED->value    => 'Unlimited Stock',
            self::SURPLUS->value      => 'Surplus Stock',
            self::OPTIMAL->value      => 'Optimal Stock',
            self::LOW->value          => 'Low Stock',
            self::CRITICAL->value     => 'Critical Stock',
            self::OUT_OF_STOCK->value => 'Out of Stock',
            self::ERROR->value        => 'Error in Stock',
        ];
    }
}
