<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Mar 2026 10:27:26 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Enums\Catalogue\Charge\ChargeStateEnum;
use App\Enums\Catalogue\Charge\ChargeTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;

trait HasBasketDetails
{
    protected function getBasketCharges(Order $order): array
    {
        return [
            'premium_dispatch' => $order->shop->charges()->where('type', ChargeTypeEnum::PREMIUM)->where('state', ChargeStateEnum::ACTIVE)->first(),
            'extra_packing'    => $order->shop->charges()->where('type', ChargeTypeEnum::PACKING)->where('state', ChargeStateEnum::ACTIVE)->first(),
            'insurance'        => $order->shop->charges()->where('type', ChargeTypeEnum::INSURANCE)->where('state', ChargeStateEnum::ACTIVE)->first(),
        ];
    }

    protected function getMissedOffers(Order $order): array
    {
        $missedOffers   = [];
        $shopOffersData = $order->shop->offers_data;

        if (Arr::get($shopOffersData, 'fob.active')) {
            $numberOrders = DB::table('orders')->where('customer_id', $order->customer_id)
                ->whereNotIn('state', [
                    OrderStateEnum::CANCELLED->value,
                    OrderStateEnum::CREATING->value,
                ])->count();

            $amountNeededToGetFob = (float)Arr::get($shopOffersData, 'fob.min_amount') - $order->gross_amount;

            if ($numberOrders === 0 && $order->gross_amount < Arr::get($shopOffersData, 'fob.min_amount')) {
                $label = __(Arr::get($shopOffersData, 'fob.missined_offer_label'), [
                    'amount'         => Number::currency($amountNeededToGetFob, $order->currency->code),
                    'percentage_off' => Arr::get($shopOffersData, 'fob.percentage_off').''
                ]);

                $missedOffers['fob'] = ['label' => $label];
            }
        }

        return $missedOffers;
    }

    protected function getGrGifts(Order $order): array
    {
        // For Retina: Basket page
        $grGifts = [
            'status'      => false,
            'is_eligible' => false,
            'gifts'       => [],
        ];

        $offersData = $order->shop->offers_data;

        $eligible     = false;
        $lastInvoiced = Cache::remember("customer_last_invoiced_at_$order->customer_id", now()->addDay(), function () use ($order) {
            return $order->customer->last_invoiced_at;
        });

        $daysSinceLastInvoiced = $lastInvoiced ? (int)-now()->diffInDays($lastInvoiced) : null;
        if ($daysSinceLastInvoiced != null && $daysSinceLastInvoiced <= Arr::get($offersData, 'gr.interval', 30)) {
            $eligible = true;
        }

        if (!$eligible) {
            return $grGifts;
        }

        $grGiftsData = Arr::get($offersData, 'gr.gifts_products');
        if ($grGiftsData) {
            $selectedGrGift = Arr::get($order->data, 'gr.selected_gift');
            foreach ($grGiftsData as $key => $gift) {
                $product = Product::find($gift['id']);
                if ($product) {
                    $grGiftsData[$key]['web_images_main'] = $product->web_images['main'];
                }

                $grGiftsData[$key]['id']   = $gift['id'];
                $grGiftsData[$key]['name'] = $gift['name'];

                if ($selectedGrGift !== null) {
                    $grGiftsData[$key]['selected'] = $gift['id'] == $selectedGrGift;
                } else {
                    $grGiftsData[$key]['selected'] = Arr::get($gift, 'default', false);
                }
            }

            $grGifts = [
                'status'                => true,
                'is_eligible'           => Arr::get($offersData, 'gr.gifts') && ($order->gross_amount >= Arr::get($offersData, 'gr.gifts_min_amount', 0)),
                'meter'                 => [$order->gross_amount, Arr::get($offersData, 'gr.gifts_min_amount', 0)],
                'gifts'                 => $grGiftsData,
                'is_gift_opted_out'     => (bool)Arr::get($order->customer->settings, 'is_gift_opted_out', false),
                'route_customer_update' => [
                    'name'       => 'retina.models.customer.update',
                    'parameters' => ['customer' => $order->customer->id],
                ],
            ];
        }

        return $grGifts;
    }
}
