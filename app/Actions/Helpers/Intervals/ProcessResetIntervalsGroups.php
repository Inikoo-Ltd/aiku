<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Helpers\Intervals;

use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrdersDispatchedToday;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrderStateFinalised;
use App\Enums\DateIntervals\DateIntervalEnum;
use App\Models\SysAdmin\Group;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessResetIntervalsGroups
{
    use AsAction;

    public string $commandSignature = 'aiku:process-reset-intervals-groups';

    public function handle(array $intervals = [], array $doPreviousPeriods = []): void
    {
        foreach (Group::all() as $group) {
            if (array_intersect($this->getIntervalValues($intervals), [
                DateIntervalEnum::YESTERDAY->value,
                DateIntervalEnum::TODAY->value
            ])) {
                GroupHydrateOrderStateFinalised::dispatch($group->id);
                GroupHydrateOrdersDispatchedToday::dispatch($group->id);
            }
        }
    }

    private function getIntervalValues(array $intervals): array
    {
        return array_map(static function ($interval) {
            if ($interval instanceof DateIntervalEnum) {
                return $interval->value;
            }

            return $interval;
        }, $intervals);
    }
}
