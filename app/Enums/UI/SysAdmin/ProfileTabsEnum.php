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
    case NOTIFICATIONS = 'notifications';
    case CLOCKING = 'clocking';
    case TIMESHEETS = 'timesheets';

    case HISTORY = 'history';
    case TODO = 'todo';
    case KPI = 'kpi';


    public function blueprint(): array
    {
        return match ($this) {
            ProfileTabsEnum::DASHBOARD => [
                'title' => __('Dashboard'),
                'icon'  => 'fal fa-clipboard-list-check',
            ],

            ProfileTabsEnum::KPI => [
                'title'   => __('KPIs'),
                'tooltip' => __('Key Performance Indicator'),
                'icon'    => 'fal fa-rabbit-fast',
            ],

            ProfileTabsEnum::TODO => [
                'title' => __('To do'),
                'icon'  => 'fal fa-clipboard-list-check',
            ],

            ProfileTabsEnum::NOTIFICATIONS => [
                'title' => __('Notifications'),
                'icon'  => 'fal fa-bell',
            ],

            ProfileTabsEnum::CLOCKING => [
                'title' => __('Clocking'),
                'icon'  => 'fal fa-user',
            ],


            ProfileTabsEnum::TIMESHEETS => [
                'title' => __('Timesheets'),
                'icon'  => 'fal fa-stopwatch',
            ],

            ProfileTabsEnum::HISTORY => [
                'title' => __('History'),
                'icon'  => 'fal fa-clock',
                'type'  => 'icon',
                'align' => 'right',
            ],
        };
    }
}
