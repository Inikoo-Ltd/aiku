<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Apr 2025 21:30:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Helpers\Dashboard;

use App\Enums\DateIntervals\DateIntervalEnum;
use Lorisleiva\Actions\Concerns\AsObject;

class DashboardIntervalFilters
{
    use asObject;

    public function handle(DateIntervalEnum $interval): string
    {
        $startDate = match($interval->value) {
            '1y' => now()->subYear(),
            '1q' => now()->subQuarter(),
            '1m' => now()->subMonth(),
            '1w' => now()->subWeek(),
            '3d' => now()->subDays(3),
            '1d' => now()->subDay(),
            'ytd' => now()->startOfYear(),
            'tdy' => now()->startOfDay(),
            'qtd' => now()->startOfQuarter(),
            'mtd' => now()->startOfMonth(),
            'wtd' => now()->startOfWeek(),
            'lm' => [now()->subMonth()->startOfMonth(), now()->subMonth()->endOfMonth()],
            'lw' => [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()],
            'ld' => [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()],
            default => null
        };

        if ($startDate == null) {
            return '';
        }

        $start = is_array($startDate) ? $startDate[0] : $startDate;
        $end = is_array($startDate) ? $startDate[1] : now();

        return str_replace('-', '', $start->toDateString()) . '-' . str_replace('-', '', $end->toDateString());

    }



}
