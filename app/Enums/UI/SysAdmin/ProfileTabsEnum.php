<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sat, 11 May 2024 23:14:46 British Summer Time, Sheffield, UK
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Enums\UI\SysAdmin;

use App\Enums\EnumHelperTrait;
use App\Enums\HasTabs;

enum ProfileTabsEnum: string
{
    use EnumHelperTrait;
    use HasTabs;

    case DASHBOARD = 'dashboard';
    case TODO = 'todo';
    case NOTIFICATIONS = 'notifications';
    case KPI = 'kpi';

    case HISTORY = 'history';
    case VISIT_LOGS = 'visit_logs';
    case TIMESHEETS = 'timesheets';

    // case MY_DATA = 'my_data';

    public function blueprint(): array
    {
        return match ($this) {

            ProfileTabsEnum::DASHBOARD => [
                'title' => __('Dashboard'),
                'icon' => 'fal fa-clipboard-list-check',
            ],
            ProfileTabsEnum::TODO => [
                'title' => __('To do'),
                'icon' => 'fal fa-clipboard-list-check',
            ],
            ProfileTabsEnum::NOTIFICATIONS => [
                'title' => __('Notifications'),
                'icon' => 'fal fa-bell',
            ],

            ProfileTabsEnum::KPI => [
                'title' => __('KPIs'),
                'tooltip' => __('key performance indicators'),
                'icon' => 'fal fa-rabbit-fast',
            ],

            ProfileTabsEnum::VISIT_LOGS => [
                'title' => __('Visit logs'),
                'icon' => 'fal fa-eye',
            ],
            ProfileTabsEnum::TIMESHEETS => [
                'title' => __('Timesheets'),
                'icon' => 'fal fa-stopwatch',
            ],

            ProfileTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon' => 'fal fa-clock',
                'type' => 'icon',
                'align' => 'right',
            ],
            // ProfileTabsEnum::MY_DATA => [
            //     'title' => __('My data'),
            //     'icon'  => 'fas fa-info-circle',
            //     'type'  => 'icon',
            //     'align' => 'right',
            // ],
        };
    }
}
