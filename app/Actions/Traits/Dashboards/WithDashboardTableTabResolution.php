<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Created: Thu, 30 Jul 2026, Bali, Indonesia
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Traits\Dashboards;

use Illuminate\Support\Arr;

trait WithDashboardTableTabResolution
{
    private const DEPRIORITISED_DASHBOARD_TABS = ['brands'];

    protected function defaultDashboardTableTab(array $tabValues): string
    {
        $preferredTabs = array_values(array_filter(
            $tabValues,
            fn (string $tabValue) => !in_array($tabValue, self::DEPRIORITISED_DASHBOARD_TABS, true)
        ));

        return Arr::first($preferredTabs) ?? Arr::first($tabValues);
    }

    protected function resolveDashboardTableTab(array $tabValues, array $userSettings, string $settingKey): string
    {
        $defaultTab = $this->defaultDashboardTableTab($tabValues);
        $currentTab = Arr::get($userSettings, $settingKey, $defaultTab);

        if (!in_array($currentTab, $tabValues, true)) {
            return $defaultTab;
        }

        return $currentTab;
    }
}
