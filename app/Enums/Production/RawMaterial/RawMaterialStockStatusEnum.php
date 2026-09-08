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

    public static function stockStatusIcon(): array
    {
        return [
            self::UNLIMITED->value    => [
                'tooltip' => __('Unlimited stock'),
                'icon'    => 'fal fa-infinity',
                'class'   => 'text-blue-500'
            ],
            self::SURPLUS->value      => [
                'tooltip' => __('Surplus stock'),
                'icon'    => 'fal fa-arrow-circle-up',
                'class'   => 'text-sky-500'
            ],
            self::OPTIMAL->value      => [
                'tooltip' => __('Optimal stock'),
                'icon'    => 'fal fa-check-circle',
                'class'   => 'text-green-500'
            ],
            self::LOW->value          => [
                'tooltip' => __('Low stock'),
                'icon'    => 'fal fa-exclamation-circle',
                'class'   => 'text-amber-500'
            ],
            self::CRITICAL->value     => [
                'tooltip' => __('Critical stock'),
                'icon'    => 'fal fa-exclamation-triangle',
                'class'   => 'text-orange-500'
            ],
            self::OUT_OF_STOCK->value => [
                'tooltip' => __('Out of stock'),
                'icon'    => 'fal fa-times-circle',
                'class'   => 'text-red-500'
            ],
            self::ERROR->value        => [
                'tooltip' => __('Error in stock'),
                'icon'    => 'fal fa-question-circle',
                'class'   => 'text-purple-500'
            ],
        ];
    }
}
