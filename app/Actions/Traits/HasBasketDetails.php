<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 10 Mar 2026 10:27:26 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use App\Enums\Catalogue\Charge\ChargeStateEnum;
use App\Enums\Catalogue\Charge\ChargeTypeEnum;
use App\Models\Catalogue\Product;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;

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

    protected function getGrGifts(Order $order): array
    {
        $grGifts = [
            'status'      => false,
            'is_eligible' => false,
            'gifts'       => [],
        ];

        $offersData = $order->shop->offers_data;

        $grGiftsData = Arr::get($offersData, 'gr.gifts_products');
        if ($grGiftsData) {
            $selectedGrGift = Arr::get($order->data, 'gr.selected_gift');
            foreach ($grGiftsData as $key => $gift) {
                $product = Product::find($gift['id']);
                if ($product) {
                    $grGiftsData[$key]['web_images_main'] = $product->web_images['main'];
                }

                $grGiftsData[$key]['id'] = $gift['id'];
                $grGiftsData[$key]['name'] = $gift['name'];

                if ($selectedGrGift) {
                    $grGiftsData[$key]['selected'] = $gift['id'] == $selectedGrGift;
                } else {
                    $grGiftsData[$key]['selected'] = Arr::get($gift, 'default', false);
                }
            }

            $grGifts = [
                'status'      => true,
                'is_eligible' => Arr::get($offersData, 'gr.gifts') && ($order->gross_amount >= Arr::get($offersData, 'gr.gifts_min_amount', 0)),
                'meter'       => [$order->gross_amount, Arr::get($offersData, 'gr.gifts_min_amount', 0)],
                'gifts'       => $grGiftsData,
            ];
        }

        return $grGifts;
    }
}
