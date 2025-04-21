<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 15 Mar 2025 21:40:31 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Providers;

use Illuminate\Support\Number;
use Illuminate\Support\ServiceProvider;

class NumberMacroServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Number::macro('abbreviateCurrency', function ($value, $currency = 'USD', $precision = 2) {
            if ($value >= 1_000_000_000) {
                $abbreviated = $value / 1_000_000_000;
                $suffix = 'B';
            } elseif ($value >= 1_000_000) {
                $abbreviated = $value / 1_000_000;
                $suffix = 'M';
            } elseif ($value >= 1_000) {
                $abbreviated = $value / 1_000;
                $suffix = 'K';
            } else {
                $abbreviated = $value;
                $suffix = '';
            }

            // Format with fixed precision to avoid values like $1.00M
            $abbreviated = round($abbreviated, $precision);

            return Number::currency($abbreviated, $currency) . $suffix;
        });

        Number::macro('delta', function ($current, $previous) {

            if (!is_numeric($current)) {
                $current = 0;
            }
            if (!is_numeric($previous)) {
                $previous = 0;
            }

            if ($current == $previous) {
                return '--';
            }

            $percentage = Number::percentage($current, $previous, 2);
            return $current > $previous ? "+$percentage" : "-$percentage";


        });

    }
}
