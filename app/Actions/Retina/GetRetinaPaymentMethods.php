<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 26 Aug 2025 23:41:13 Central Standard Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina;

use Illuminate\Database\Eloquent\Collection;
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

        /** @var PaymentAccountShop $paymentAccountShop */
        foreach ($this->checkoutPaymentAccountShops($order) as $paymentAccountShop) {
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

    /**
     * The ways this basket can be paid. Pastpay reserves and cash on delivery collects the whole
     * invoice when the goods are sent, which cannot take a deposit now and a balance later; a bank
     * transfer arrives after the order is placed, and pre-orders are paid at checkout, in full or the
     * deposit (HELP-3678). A basket with pre-order lines offers nothing until the terms are accepted.
     *
     * @return Collection<int, PaymentAccountShop>
     */
    public function checkoutPaymentAccountShops(Order $order): Collection
    {
        $basketPreOrders  = $order->shop->hasPreOrders() && !$order->preOrder ? GetBasketPreOrders::run($order) : null;
        if ($basketPreOrders && $basketPreOrders['has_pre_orders'] && !$basketPreOrders['is_accepted']) {
            return new Collection();
        }

        $hasPreOrders     = $order->preOrder || ($basketPreOrders['has_pre_orders'] ?? false);
        $excludedPayments = $hasPreOrders ? [PaymentAccountTypeEnum::PASTPAY, PaymentAccountTypeEnum::CASH_ON_DELIVERY, PaymentAccountTypeEnum::BANK] : [];

        return $order->shop->paymentAccountShops()
            ->where('state', PaymentAccountShopStateEnum::ACTIVE)
            ->where('show_in_checkout', true)
            ->when($excludedPayments, fn ($query) => $query->whereNotIn('type', $excludedPayments))
            ->orderby('checkout_display_position')
            ->get();
    }
}
