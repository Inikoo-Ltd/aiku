<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 29 Oct 2025 23:18:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\Hydrators;

use App\Actions\Traits\WithEnumStats;
use App\Enums\Ordering\Order\OrderPayStatusEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Shop;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Lorisleiva\Actions\Concerns\AsAction;

class ShopHydrateOrderStateSubmitted implements ShouldBeUnique
{
    use AsAction;
    use WithEnumStats;


    public string $jobQueue = 'sales';

    public function getJobUniqueId(int $shopId): int
    {
        return $shopId;
    }

    public function handle(int $shopId): void
    {
        $shop = Shop::find($shopId);
        if (!$shop) {
            return;
        }

        $stats = [
            'number_orders_state_submitted'              => $shop->orders()->where('state', OrderStateEnum::SUBMITTED)->count(),
            'orders_state_submitted_amount'              => $shop->orders()->where('state', OrderStateEnum::SUBMITTED)->sum('net_amount'),
            'orders_state_submitted_amount_org_currency' => $shop->orders()->where('state', OrderStateEnum::SUBMITTED)->sum('org_net_amount'),
            'orders_state_submitted_amount_grp_currency' => $shop->orders()->where('state', OrderStateEnum::SUBMITTED)->sum('grp_net_amount'),

            'number_orders_state_submitted_paid'              => $shop->orders()->where('state', OrderStateEnum::SUBMITTED)
                ->whereIn('orders.pay_status', [OrderPayStatusEnum::PAID, OrderPayStatusEnum::NO_NEED])
                ->count(),
            'orders_state_submitted_paid_amount'              => $shop->orders()->where('state', OrderStateEnum::SUBMITTED)
                ->whereIn('orders.pay_status', [OrderPayStatusEnum::PAID, OrderPayStatusEnum::NO_NEED])
                ->sum('net_amount'),
            'orders_state_submitted_paid_amount_org_currency' => $shop->orders()->where('state', OrderStateEnum::SUBMITTED)
                ->whereIn('orders.pay_status', [OrderPayStatusEnum::PAID, OrderPayStatusEnum::NO_NEED])
                ->sum('org_net_amount'),
            'orders_state_submitted_paid_amount_grp_currency' => $shop->orders()->where('state', OrderStateEnum::SUBMITTED)
                ->whereIn('orders.pay_status', [OrderPayStatusEnum::PAID, OrderPayStatusEnum::NO_NEED])
                ->sum('grp_net_amount'),

            'number_orders_state_submitted_not_paid'              => $shop->orders()->where('state', OrderStateEnum::SUBMITTED)
                ->whereIn('orders.pay_status', [OrderPayStatusEnum::UNPAID, OrderPayStatusEnum::UNKNOWN])
                ->count(),
            'orders_state_submitted_not_paid_amount'              => $shop->orders()->where('state', OrderStateEnum::SUBMITTED)
                ->whereIn('orders.pay_status', [OrderPayStatusEnum::UNPAID, OrderPayStatusEnum::UNKNOWN])
                ->sum('net_amount'),
            'orders_state_submitted_not_paid_amount_org_currency' => $shop->orders()->where('state', OrderStateEnum::SUBMITTED)
                ->whereIn('orders.pay_status', [OrderPayStatusEnum::UNPAID, OrderPayStatusEnum::UNKNOWN])
                ->sum('org_net_amount'),
            'orders_state_submitted_not_paid_amount_grp_currency' => $shop->orders()->where('state', OrderStateEnum::SUBMITTED)
                ->whereIn('orders.pay_status', [OrderPayStatusEnum::UNPAID, OrderPayStatusEnum::UNKNOWN])
                ->sum('grp_net_amount'),

        ];


        $shop->orderHandlingStats()->update($stats);
    }


}
