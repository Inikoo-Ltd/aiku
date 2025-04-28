<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 14 Mar 2025 21:14:13 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Dashboards;

use App\Actions\Helpers\Dashboard\DashboardIntervalFilters;
use App\Enums\DateIntervals\DateIntervalEnum;

trait WithDashboardIntervalOption
{
    public function dashboardIntervalOption(): array
    {
        return collect(DateIntervalEnum::cases())->map(function ($interval) {
            return [
                'label'               => $interval->labels()[$interval->value],
                'labelShort'          => $interval->shortLabels()[$interval->value],
                'value'               => $interval->value,
                'route_interval_args' => DashboardIntervalFilters::run($interval)
            ];
        })->toArray();
    }
}
