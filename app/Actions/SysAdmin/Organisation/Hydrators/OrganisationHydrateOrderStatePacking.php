<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>  
 * Created: Wed, 25 Feb 2026 19:58:57 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\SysAdmin\Organisation;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationHydrateOrderStatePacking implements ShouldBeUnique
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


            'number_orders_state_packing'              => $organisation->orderFromActiveShops()->where('state', OrderStateEnum::PACKED)->count(),
            'orders_state_packing_amount_org_currency' => $organisation->orderFromActiveShops()->where('state', OrderStateEnum::PACKED)->sum('org_net_amount'),
            'orders_state_packing_amount_grp_currency' => $organisation->orderFromActiveShops()->where('state', OrderStateEnum::PACKED)->sum('grp_net_amount'),

            'number_orders_packing_today'              => $organisation->orderFromActiveShops()->whereDate('packing_at', Carbon::today())->count(),
            'orders_packing_today_amount_org_currency' => $organisation->orderFromActiveShops()->whereDate('packing_at', Carbon::today())->sum('org_net_amount'),
            'orders_packing_today_amount_grp_currency' => $organisation->orderFromActiveShops()->whereDate('packing_at', Carbon::today())->sum('grp_net_amount'),


        ];

        $organisation->orderHandlingStats()->update($stats);
    }


}
