<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 09 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Enums\GoodsIn\StockDeliveryItem;

use App\Enums\EnumHelperTrait;

/**
 * What the checked quantity says against the expected one. A received count that is a clean multiple
 * (or fraction) of the expected one is far more often a supplier invoicing in another unit than a real
 * shortage, so it is flagged as a possible unit mismatch instead of under or over.
 */
enum StockDeliveryItemDiscrepancyEnum: string
{
    use EnumHelperTrait;

    case UNDER = 'under';
    case OVER = 'over';
    case POSSIBLE_UNIT_MISMATCH = 'possible_unit_mismatch';
    case WITHIN_TOLERANCE = 'within_tolerance';

    public const array UNIT_MISMATCH_FACTORS = [2, 4, 5, 10, 12, 20, 24, 25, 50, 100, 1000];

    public static function labels(): array
    {
        return [
            'under'                  => __('Under delivered'),
            'over'                   => __('Over delivered'),
            'possible_unit_mismatch' => __('Possible unit mismatch'),
            'within_tolerance'       => __('Within tolerance'),
        ];
    }

    public static function isFlagged(?self $discrepancy): bool
    {
        return $discrepancy !== null && $discrepancy !== self::WITHIN_TOLERANCE;
    }

    /**
     * @param  float  $toleranceAmount  in the same currency as the line amount
     */
    public static function classify(float $expected, float $received, float $lineAmount, float $tolerancePercentage = 0, float $toleranceAmount = 0): ?self
    {
        $difference = $received - $expected;
        if (abs($difference) < 0.00005) {
            return null;
        }

        if ($expected > 0 && $received > 0) {
            $ratio = $received > $expected ? $received / $expected : $expected / $received;
            foreach (self::UNIT_MISMATCH_FACTORS as $factor) {
                if (abs($ratio - $factor) < 0.0001) {
                    return self::POSSIBLE_UNIT_MISMATCH;
                }
            }
        }

        $differenceAmount = $expected > 0 ? abs($difference) * $lineAmount / $expected : 0;
        $percentage       = $expected > 0 ? abs($difference) * 100 / $expected : 100;

        $hasTolerance     = $tolerancePercentage > 0 || $toleranceAmount > 0;
        $withinPercentage = $tolerancePercentage <= 0 || $percentage <= $tolerancePercentage;
        $withinAmount     = $toleranceAmount <= 0 || $differenceAmount <= $toleranceAmount;
        if ($hasTolerance && $withinPercentage && $withinAmount) {
            return self::WITHIN_TOLERANCE;
        }

        return $difference < 0 ? self::UNDER : self::OVER;
    }
}
