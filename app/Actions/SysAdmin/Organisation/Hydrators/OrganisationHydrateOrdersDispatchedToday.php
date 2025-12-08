<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 29 Oct 2025 22:04:55 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Models\SysAdmin\Organisation;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationHydrateOrdersDispatchedToday implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;

    public string $jobQueue = 'sales';

    public function getJobUniqueId(int $organisationID): string
    {
        return $organisationID;
    }

    public function handle(int $organisationID): void
    {
        $organisation = Organisation::find($organisationID);
        if (! $organisation) {
            return;
        }
        $stats = [

            'number_orders_dispatched_today' => $organisation->orders()->whereDate('dispatched_at', Carbon::today())->count(),
            'orders_dispatched_today_amount_org_currency' => $organisation->orders()->whereDate('dispatched_at', Carbon::today())->sum('org_net_amount'),
            'orders_dispatched_today_amount_grp_currency' => $organisation->orders()->whereDate('dispatched_at', Carbon::today())->sum('grp_net_amount'),

        ];

        $organisation->orderHandlingStats()->update($stats);
    }
}
