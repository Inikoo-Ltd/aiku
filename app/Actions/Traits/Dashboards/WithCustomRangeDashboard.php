<?php

/*
 * Author: Steven Wicca stewicalf@gmail.com
 * Created: Wed, 26 Nov 2025 16:21:33 Central Indonesia Time, Lembeng Beach, Bali, Indonesia
 * Copyright (c) 2025, Steven Wicca Alfredo
 */

namespace App\Actions\Traits\Dashboards;

use App\Enums\DateIntervals\DateIntervalEnum;
use App\Models\Catalogue\Shop;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use App\Services\CustomRangeDataService;
use Illuminate\Support\Arr;

trait WithCustomRangeDashboard
{
    protected function getCustomRangeData(Group|Organisation|Shop $parent, string $startDate, string $endDate): array
    {
        $customRangeService = app(CustomRangeDataService::class);

        if ($parent instanceof Group) {
            return $customRangeService->getGroupCustomRangeData($parent, $startDate, $endDate);
        }

        if ($parent instanceof Organisation) {
            return $customRangeService->getOrganisationCustomRangeData($parent, $startDate, $endDate);
        }

        if ($parent instanceof Shop) {
            return $customRangeService->getShopCustomRangeData($parent, $startDate, $endDate);
        }

        return [];
    }

    protected function setupCustomRange(array $userSettings, $parent): array
    {
        $customRangeData = [];
        $saved_interval = DateIntervalEnum::tryFrom(Arr::get($userSettings, 'selected_interval', 'all')) ?? DateIntervalEnum::ALL;

        if ($saved_interval === DateIntervalEnum::CUSTOM) {
            $rangeInterval = Arr::get($userSettings, 'range_interval', '');
            if ($rangeInterval) {
                $dates = explode('-', $rangeInterval);
                if (count($dates) === 2) {
                    $customRangeData = $this->getCustomRangeData($parent, $dates[0], $dates[1]);
                }
            }
        }

        return $customRangeData;
    }
}
