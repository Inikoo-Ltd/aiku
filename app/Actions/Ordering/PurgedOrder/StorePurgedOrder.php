<?php

/*
 * author Arya Permana - Kirin
 * created on 04-11-2024-08h-57m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Ordering\PurgedOrder;

use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Models\Ordering\Order;
use App\Models\Ordering\Purge;
use App\Models\Ordering\PurgedOrder;

class StorePurgedOrder extends OrgAction
{
    use WithNoStrictRules;

    public function handle(Purge $purge, Order $order): PurgedOrder
    {
        data_set($modelData, 'group_id', $order->group_id);
        data_set($modelData, 'organisation_id', $order->organisation_id);
        data_set($modelData, 'shop_id', $order->shop_id);
        data_set($modelData, 'order_id', $order->id);
        data_set($modelData, 'customer_id', $order->customer_id);
        data_set($modelData, 'order_last_updated_at', $order->updated_at);
        data_set($modelData, 'net_amount', $order->net_amount);
        data_set($modelData, 'org_net_amount', $order->org_net_amount);
        data_set($modelData, 'grp_net_amount', $order->grp_net_amount);
        data_set($modelData, 'number_transactions', $order->transactions->count());

        return $purge->purgedOrders()->create($modelData);
    }


    public function rules(): array
    {
        $rules = [
        ];

        if (!$this->strict) {
            $rules = $this->noStrictStoreRules($rules);
        }

        return $rules;
    }

    public function action(Purge $purge, Order $order, array $modelData, int $hydratorsDelay = 0, bool $strict = true): PurgedOrder
    {
        $this->asAction       = true;
        $this->strict         = $strict;
        $this->hydratorsDelay = $hydratorsDelay;

        $this->initialisationFromShop($purge->shop, $modelData);
        return $this->handle($purge, $order);
    }
}
