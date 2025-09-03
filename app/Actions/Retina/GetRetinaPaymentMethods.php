<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 26 Aug 2025 23:41:13 Central Standard Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina;

use App\Actions\Accounting\PaymentAccountShop\UI\GetRetinaPaymentAccountShopData;
use App\Enums\Accounting\PaymentAccount\PaymentAccountTypeEnum;
use App\Enums\Accounting\PaymentAccountShop\PaymentAccountShopStateEnum;
use App\Models\Accounting\OrderPaymentApiPoint;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Ordering\Order;
use Lorisleiva\Actions\Concerns\AsObject;

class GetRetinaPaymentMethods
{
    use AsObject;

    public function handle(Order $order, OrderPaymentApiPoint $orderPaymentApiPoint): array
    {
        $paymentMethods = [];

        $paymentMethodsData = [];

        $paymentAccountShops = $order->shop->paymentAccountShops()
            ->where('state', PaymentAccountShopStateEnum::ACTIVE)
            ->where('show_in_checkout', true)
            ->orderby('checkout_display_position')
            ->get();
        /** @var PaymentAccountShop $paymentAccountShop */
        foreach ($paymentAccountShops as $paymentAccountShop) {
            $paymentAccountShopData = GetRetinaPaymentAccountShopData::run($order, $paymentAccountShop, $orderPaymentApiPoint);


            if ($paymentAccountShopData) {
                if ($paymentAccountShop->type == PaymentAccountTypeEnum::CHECKOUT) {
                    $paymentMethodsData[$paymentAccountShop->type->value] = $paymentAccountShop->id;
                }
                $paymentMethods[] = $paymentAccountShopData;
            }
        }


        $orderPaymentApiPoint->update([
            'data' => [
                'payment_methods' => $paymentMethodsData,
            ]
        ]);

        return $paymentMethods;
    }
}
