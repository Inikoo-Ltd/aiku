<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Masters\UI;

use App\Actions\Masters\MasterShop\GetMasterShopTimeSeriesStats;
use App\Models\SysAdmin\Group;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsObject;

class GetMastersDashboardTimeSeriesData
{
    use AsObject;

    public function handle(Group $group, $fromDate = null, $toDate = null, ?bool $useCache = null): array
    {
        $useCache = $useCache ?? true;

        if (!$useCache) {
            return $this->fetchData($group, $fromDate, $toDate);
        }

        $cacheKey = $this->getCacheKey($group, $fromDate, $toDate);

        return Cache::remember($cacheKey, now()->addSeconds(300), function () use ($group, $fromDate, $toDate) {
            return $this->fetchData($group, $fromDate, $toDate);
        });
    }

    protected function getCacheKey(Group $group, $fromDate, $toDate): string
    {
        return sprintf(
            'dashboard:masters_timeseries:%s:%s:%s',
            $group->id,
            $fromDate ?? 'null',
            $toDate ?? 'null'
        );
    }

    protected function fetchData(Group $group, $fromDate, $toDate): array
    {
        return [
            'masterShops' => GetMasterShopTimeSeriesStats::run($group, $fromDate, $toDate),
        ];
    }

    public static function clearCache(Group $group, $fromDate = null, $toDate = null): void
    {
        $instance = new static();
        $cacheKey = $instance->getCacheKey($group, $fromDate, $toDate);

        Cache::forget($cacheKey);
    }
}
