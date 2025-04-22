<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 23 Apr 2025 01:36:18 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation\Hydrators;

use App\Actions\Traits\Hydrators\WithHydrateBasket;
use App\Actions\Traits\Hydrators\WithIntervalUniqueJob;
use App\Actions\Traits\WithIntervalsAggregators;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationHydrateOrderInBasketAtCustomerUpdateIntervals implements ShouldBeUnique
{
    use AsAction;
    use WithIntervalsAggregators;
    use WithIntervalUniqueJob;
    use WithHydrateBasket;

    public string $jobQueue = 'sales';

    public function getJobUniqueId(Organisation $organisation, ?array $intervals = null, ?array $doPreviousPeriods = null): string
    {
        return $this->getUniqueJobWithInterval($organisation, $intervals, $doPreviousPeriods);
    }

    public function handle(Organisation $organisation, ?array $intervals = null, ?array $doPreviousPeriods = null): void
    {
        $organisation->orderingIntervals()->update(
            $this->getBasketCountStats('updated_at', $organisation, $intervals, $doPreviousPeriods),
        );

        $organisation->salesIntervals()->update(
            $this->getBasketNetAmountStats('updated_at', 'org', $organisation, $intervals, $doPreviousPeriods),
        );

        $organisation->salesIntervals()->update(
            $this->getBasketNetAmountStats('updated_at', 'grp', $organisation, $intervals, $doPreviousPeriods),
        );
    }

}
