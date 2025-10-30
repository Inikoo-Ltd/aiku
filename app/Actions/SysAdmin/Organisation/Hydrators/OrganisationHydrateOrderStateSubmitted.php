<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 29 Oct 2025 22:18:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\SysAdmin\Organisation\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Ordering\Order\OrderPayStatusEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\SysAdmin\Organisation;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class OrganisationHydrateOrderStateSubmitted implements ShouldBeUnique
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


            'number_orders_state_submitted'              => $organisation->orders()->where('state', OrderStateEnum::SUBMITTED)->count(),
            'orders_state_submitted_amount_org_currency' => $organisation->orders()->where('state', OrderStateEnum::SUBMITTED)->sum('org_net_amount'),
            'orders_state_submitted_amount_grp_currency' => $organisation->orders()->where('state', OrderStateEnum::SUBMITTED)->sum('grp_net_amount'),


            'number_orders_state_submitted_paid'              => $organisation->orders()->where('state', OrderStateEnum::SUBMITTED)
                ->whereIn('orders.pay_status', [OrderPayStatusEnum::PAID, OrderPayStatusEnum::NO_NEED])
                ->count(),
            'orders_state_submitted_paid_amount_org_currency' => $organisation->orders()->where('state', OrderStateEnum::SUBMITTED)
                ->whereIn('orders.pay_status', [OrderPayStatusEnum::PAID, OrderPayStatusEnum::NO_NEED])
                ->sum('grp_org_amount'),

            'orders_state_submitted_paid_amount_grp_currency' => $organisation->orders()->where('state', OrderStateEnum::SUBMITTED)
                ->whereIn('orders.pay_status', [OrderPayStatusEnum::PAID, OrderPayStatusEnum::NO_NEED])
                ->sum('grp_net_amount'),

            'number_orders_state_submitted_not_paid'              => $organisation->orders()->where('state', OrderStateEnum::SUBMITTED)
                ->whereIn('orders.pay_status', [OrderPayStatusEnum::UNPAID, OrderPayStatusEnum::UNKNOWN])
                ->count(),
            'orders_state_submitted_not_paid_amount_org_currency' => $organisation->orders()->where('state', OrderStateEnum::SUBMITTED)
                ->whereIn('orders.pay_status', [OrderPayStatusEnum::UNPAID, OrderPayStatusEnum::UNKNOWN])
                ->sum('org_net_amount'),
            'orders_state_submitted_not_paid_amount_grp_currency' => $organisation->orders()->where('state', OrderStateEnum::SUBMITTED)
                ->whereIn('orders.pay_status', [OrderPayStatusEnum::UNPAID, OrderPayStatusEnum::UNKNOWN])
                ->sum('grp_net_amount'),

        ];

        $organisation->orderHandlingStats()->update($stats);
    }


}
