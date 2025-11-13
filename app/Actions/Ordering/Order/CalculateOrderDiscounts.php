<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 02 Oct 2025 16:13:22 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Ordering\Order;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class CalculateOrderDiscounts
{
    use AsObject;

    public function handle(Order $order): Order
    {

        $allowances=[];

        $enabledOffers = $this->getEnabledOffers($order);

        $this->processAllowances($order, $enabledOffers,$allowances);

        return $order;
    }

    private function getEnabledOffers(Order $order): array
    {
        $offersTriggerCustomer = [];

        $offersData = DB::table('offers')->select(['id','type','trigger_data','allowance_signature'])->where('shop_id', $order->shop_id)->where('status', true)->where('trigger_type', 'Customer')->get();
        foreach ($offersData as $offerData) {

            if ($offerData->type == 'Amount AND Order Number') {
                if ($this->checkAmountAndOrderNumber($order, $offerData)) {
                    $offersTriggerCustomer[$offerData->allowance_signature] = $offerData->id;
                }
            }

        }


        return $offersTriggerCustomer;
    }

    public function checkAmountAndOrderNumber($order, $offerData): bool
    {

        $triggerData = json_decode($offerData->trigger_data, true);

        if ($order->goods_amount < $triggerData['min_amount']) {
            return false;
        }

        $numberOrders = DB::table('orders')->where('customer_id', $order->customer_id)
            ->whereNotIn('state', [
                OrderStateEnum::CANCELLED->value,
                OrderStateEnum::CREATING->value,
            ])->count();

        if ($numberOrders > $triggerData['order_number']) {
            return false;
        }

        return true;


    }

    public function processAllowances(Order $order, array $enabledOffers,array $allowances): array
    {
        foreach ($enabledOffers as $offerId) {
            $allowances=$this->processAllowance($order, $offerId,$allowances);
        }

        return $allowances;
    }

    public function processAllowance(Order $order, $offerId,array $allowances): array
    {
        $allowances = DB::table('offer_allowances')->where('offer_id', $offerId)->first();
        if ($offer) {
            $offerData = json_decode($offer->trigger_data, true);
            $order->total_amount -= $offerData['discount_amount'];
        }

        return $allowances;
    }

}
