<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 29 Sep 2026, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Shop\SalesTarget\Concerns;

use App\Actions\Helpers\CurrencyExchange\GetCurrencyExchange;
use App\Enums\Catalogue\Shop\ShopStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Shop;
use App\Models\Catalogue\ShopSalesTarget;
use App\Models\SysAdmin\Group;
use App\Models\SysAdmin\Organisation;
use Illuminate\Support\Facades\DB;

trait HasOrdersPipeline
{
    public const array PIPELINE_STATES = [
        OrderStateEnum::SUBMITTED,
        OrderStateEnum::IN_WAREHOUSE,
        OrderStateEnum::HANDLING,
        OrderStateEnum::HANDLING_BLOCKED,
        OrderStateEnum::PICKED,
        OrderStateEnum::PACKING,
        OrderStateEnum::PACKED,
    ];

    /**
     * @return array{amount: float, orders: int, submitted_amount: float, in_warehouse_amount: float}
     */
    private function pipeline(Shop|Organisation|Group $parent): array
    {
        $rows = DB::table('orders')
            ->whereIn('orders.shop_id', $this->salesShopIds($parent))
            ->whereIn('orders.state', array_map(fn (OrderStateEnum $state) => $state->value, self::PIPELINE_STATES))
            ->whereNull('orders.deleted_at')
            ->whereNotExists(fn ($query) => $query->from('org_partners')->whereColumn('org_partners.customer_id', 'orders.customer_id'))
            ->selectRaw('orders.state = ? as is_submitted, count(*) as orders, coalesce(sum(orders.'.($parent instanceof Group ? 'grp_net_amount' : 'org_net_amount').'), 0) as amount', [OrderStateEnum::SUBMITTED->value])
            ->groupByRaw('1')
            ->get();

        $submitted   = (float) ($rows->firstWhere('is_submitted', true)->amount ?? 0);
        $inWarehouse = (float) ($rows->firstWhere('is_submitted', false)->amount ?? 0);

        return [
            'amount'              => round($submitted + $inWarehouse, 2),
            'orders'              => (int) $rows->sum('orders'),
            'submitted_amount'    => round($submitted, 2),
            'in_warehouse_amount' => round($inWarehouse, 2),
        ];
    }

    /**
     * @return list<int>
     */
    private function salesShopIds(Shop|Organisation|Group $parent): array
    {
        return $parent instanceof Shop ? [$parent->id] : $parent->shops()->pluck('id')->all();
    }

    /**
     * Closed shops still count in sales history but no longer carry a target.
     *
     * @return list<int>
     */
    private function targetShopIds(Shop|Organisation|Group $parent): array
    {
        return $parent instanceof Shop ? [$parent->id] : $parent->shops()->where('state', '!=', ShopStateEnum::CLOSED)->pluck('id')->all();
    }

    private function salesColumn(Shop|Organisation|Group $parent): string
    {
        return $parent instanceof Group ? 'sales_grp_currency_external' : 'sales_org_currency_external';
    }

    /**
     * @return list<string>
     */
    private function targetRelations(Shop|Organisation|Group $parent): array
    {
        return $parent instanceof Group ? ['setBy', 'shop.organisation.currency'] : ['setBy'];
    }

    private function currencyCode(Shop|Organisation|Group $parent): string
    {
        return ($parent instanceof Shop ? $parent->organisation : $parent)->currency->code;
    }

    /**
     * Targets are set in the organisation's currency; the group adds them up in its own at
     * today's rate. Without a rate the shop keeps its default target instead of a wrong one.
     */
    private function targetInParentCurrency(ShopSalesTarget $target, Shop|Organisation|Group $parent): ?float
    {
        $amount = (float) $target->target_org_currency;

        if (!$parent instanceof Group) {
            return $amount;
        }

        $rate = GetCurrencyExchange::run($target->shop->organisation->currency, $parent->currency);

        return $rate ? $amount * $rate : null;
    }
}
