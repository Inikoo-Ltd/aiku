<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Apr 2025 01:36:35 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Group\Hydrators;

use App\Actions\Traits\Hydrators\WithHydrateBasket;
use App\Actions\Traits\Hydrators\WithIntervalUniqueJob;
use App\Actions\Traits\WithIntervalsAggregators;
use App\Models\SysAdmin\Group;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class GroupHydrateOrderInBasketAtCreatedIntervals implements ShouldBeUnique
{
    use AsAction;
    use WithIntervalsAggregators;
    use WithIntervalUniqueJob;
    use WithHydrateBasket;

    public string $jobQueue = 'sales';

    public function getJobUniqueId(Group $group, ?array $intervals = null, ?array $doPreviousPeriods = null): string
    {
        return $this->getUniqueJobWithInterval($group, $intervals, $doPreviousPeriods);
    }

    public function handle(Group $group, ?array $intervals = null, ?array $doPreviousPeriods = null): void
    {
        $group->orderingIntervals()->update(
            $this->getBasketCountStats('created_at', $group, $intervals, $doPreviousPeriods),
        );


        $group->salesIntervals()->update(
            $this->getBasketNetAmountStats('created_at', 'grp', $group, $intervals, $doPreviousPeriods),
        );
    }

}
