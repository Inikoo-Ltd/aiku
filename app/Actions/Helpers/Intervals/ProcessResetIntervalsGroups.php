<?php

/*
 * Author: stewicca <stewicalf@gmail.com>
 * Copyright (c) 2026, Steven Wicca Alfredo
 */

namespace App\Actions\Helpers\Intervals;

use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateInvoiceIntervals;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrderInBasketAtCreatedIntervals;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrderInBasketAtCustomerUpdateIntervals;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrderIntervals;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrdersDispatchedToday;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateOrderStateFinalised;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateRegistrationIntervals;
use App\Actions\SysAdmin\Group\Hydrators\GroupHydrateSalesIntervals;
use App\Enums\DateIntervals\DateIntervalEnum;
use App\Models\SysAdmin\Group;
use Lorisleiva\Actions\Concerns\AsAction;

class ProcessResetIntervalsGroups
{
    use AsAction;

    public function handle(array $intervals, array $doPreviousPeriods): void
    {
        foreach (Group::all() as $group) {
            if (array_intersect($this->getIntervalValues($intervals), [
                DateIntervalEnum::YESTERDAY->value,
                DateIntervalEnum::TODAY->value
            ])) {
                GroupHydrateOrderStateFinalised::dispatch($group->id);
                GroupHydrateOrdersDispatchedToday::dispatch($group->id);
            }

            GroupHydrateSalesIntervals::dispatch(
                group: $group,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods,
            );
            GroupHydrateInvoiceIntervals::dispatch(
                group: $group,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            );
            GroupHydrateRegistrationIntervals::dispatch(
                groupId: $group->id,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            );

            GroupHydrateOrderIntervals::dispatch(
                group: $group,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            );
            GroupHydrateOrderInBasketAtCreatedIntervals::dispatch(
                group: $group,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            );

            GroupHydrateOrderInBasketAtCustomerUpdateIntervals::dispatch(
                group: $group,
                intervals: $intervals,
                doPreviousPeriods: $doPreviousPeriods
            );
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
