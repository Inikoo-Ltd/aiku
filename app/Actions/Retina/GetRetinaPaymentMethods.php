<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 26 Aug 2025 23:41:13 Central Standard Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina;

use App\Actions\Accounting\PaymentAccountShop\UI\GetRetinaPaymentAccountShopData;
use App\Actions\Ordering\PreOrder\GetBasketPreOrders;
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

        /** Pastpay reserves and cash on delivery collects the whole invoice when the goods are
         * sent, which cannot take a deposit now and a balance later; a bank transfer arrives after
         * the order is placed, so it cannot be the made-to-order deposit taken at checkout (HELP-3432). */
        $basketPreOrders  = $order->shop->hasPreOrders() && !$order->preOrder ? GetBasketPreOrders::run($order) : null;
        $hasPreOrders     = $order->preOrder || ($basketPreOrders['has_pre_orders'] ?? false);
        $excludedPayments = $hasPreOrders ? [PaymentAccountTypeEnum::PASTPAY, PaymentAccountTypeEnum::CASH_ON_DELIVERY] : [];
        if ($order->preOrder || ($basketPreOrders['deferred_amount'] ?? 0) > 0) {
            $excludedPayments[] = PaymentAccountTypeEnum::BANK;
        }

        $paymentAccountShops = $order->shop->paymentAccountShops()
            ->where('state', PaymentAccountShopStateEnum::ACTIVE)
            ->where('show_in_checkout', true)
            ->when($excludedPayments, fn ($query) => $query->whereNotIn('type', $excludedPayments))
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
