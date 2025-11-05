<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 29 Oct 2025 22:17:08 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\SysAdmin\Organisation;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationHydrateOrderStatePacked implements ShouldBeUnique
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
        if (!$organisation) {
            return;
        }
        $stats = [


            'number_orders_state_packed'              => $organisation->orders()->where('state', OrderStateEnum::PACKED)->count(),
            'orders_state_packed_amount_org_currency' => $organisation->orders()->where('state', OrderStateEnum::PACKED)->sum('org_net_amount'),
            'orders_state_packed_amount_grp_currency' => $organisation->orders()->where('state', OrderStateEnum::PACKED)->sum('grp_net_amount'),

            'number_orders_packed_today'              => $organisation->orders()->whereDate('packed_at', Carbon::today())->count(),
            'orders_packed_today_amount_org_currency' => $organisation->orders()->whereDate('packed_at', Carbon::today())->sum('org_net_amount'),
            'orders_packed_today_amount_grp_currency' => $organisation->orders()->whereDate('packed_at', Carbon::today())->sum('grp_net_amount'),


        ];

        $organisation->orderHandlingStats()->update($stats);
    }


}
