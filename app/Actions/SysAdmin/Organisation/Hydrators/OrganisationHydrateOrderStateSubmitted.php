<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 29 Oct 2025 22:18:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation\Hydrators;

use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationHydrateOrderStateSubmitted implements ShouldBeUnique
{
    use AsAction;

    public function getJobUniqueId(int $organisationID): string
    {
        return (string) $organisationID;
    }

    public function handle(int $organisationID): void
    {
        $organisation = Organisation::find($organisationID);
        if (!$organisation) {
            return;
        }
        $stats = [


            'number_orders_state_submitted'              => $organisation->orders()->where('state', OrderStateEnum::SUBMITTED)->count(),
            'orders_state_submitted_amount_org_currency' => $organisation->orders()->where('state', OrderStateEnum::SUBMITTED)->sum('org_net_amount'),
            'orders_state_submitted_amount_grp_currency' => $organisation->orders()->where('state', OrderStateEnum::SUBMITTED)->sum('grp_net_amount'),


            'number_orders_state_submitted_paid'              => $organisation->orders()->where('state', OrderStateEnum::SUBMITTED)
                ->paySettled()
                ->count(),
            'orders_state_submitted_paid_amount_org_currency' => $organisation->orders()->where('state', OrderStateEnum::SUBMITTED)
                ->paySettled()
                ->sum('org_net_amount'),

            'orders_state_submitted_paid_amount_grp_currency' => $organisation->orders()->where('state', OrderStateEnum::SUBMITTED)
                ->paySettled()
                ->sum('grp_net_amount'),

            'number_orders_state_submitted_not_paid'              => $organisation->orders()->where('state', OrderStateEnum::SUBMITTED)
                ->payNotSettled()
                ->count(),
            'orders_state_submitted_not_paid_amount_org_currency' => $organisation->orders()->where('state', OrderStateEnum::SUBMITTED)
                ->payNotSettled()
                ->sum('org_net_amount'),
            'orders_state_submitted_not_paid_amount_grp_currency' => $organisation->orders()->where('state', OrderStateEnum::SUBMITTED)
                ->payNotSettled()
                ->sum('grp_net_amount'),

        ];

        $organisation->orderHandlingStats()->update($stats);
    }


}
