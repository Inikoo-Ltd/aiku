<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 06 Nov 2025 12:47:35 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Http\Resources\Traits;

trait HasPriceMetrics
{
    /**
     * Compute common pricing metrics used in resources.
     *
     *
     * @return array{0:mixed,1:mixed,2:mixed,3:int} [$margin, $rrpPerUnit, $profit, $unitsInt]
     */
    protected function getPriceMetrics(float|int|null $rrp, float|int $price, float|int $units): array
    {
        $margin     = '';
        $rrpPerUnit = '';
        $profit     = '';
        $unitsInt   = (int) $units;

        if ($rrp > 0) {
            // Avoid division by zero for per-unit calculation
            $safeUnits = max(1, $unitsInt);
            $margin     = percentage(round((($rrp - $price) / $rrp) * 100, 1), 100);
            $rrpPerUnit = round($rrp / $safeUnits, 2);
            $profit     = round($rrp - $price, 2);
            $profitPerUnit     = round($profit / $safeUnits, 2);
        }

        return [$margin, $rrpPerUnit, $profit, $profitPerUnit,$unitsInt];
    }
}
