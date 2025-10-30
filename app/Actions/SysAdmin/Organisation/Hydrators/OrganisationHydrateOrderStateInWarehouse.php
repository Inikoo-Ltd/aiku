<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 29 Oct 2025 20:34:37 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationHydrateOrderStateInWarehouse implements ShouldBeUnique
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

            'number_orders_state_in_warehouse'              => $organisation->orders()->where('state', OrderStateEnum::IN_WAREHOUSE)->count(),
            'orders_state_in_warehouse_amount_org_currency' => $organisation->orders()->where('state', OrderStateEnum::IN_WAREHOUSE)->sum('grp_org_amount'),
            'orders_state_in_warehouse_amount_grp_currency' => $organisation->orders()->where('state', OrderStateEnum::IN_WAREHOUSE)->sum('grp_net_amount'),

        ];

        $organisation->orderHandlingStats()->update($stats);
    }


}
