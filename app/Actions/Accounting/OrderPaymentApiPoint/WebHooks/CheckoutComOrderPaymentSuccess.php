<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 May 2025 12:38:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\OrderPaymentApiPoint\WebHooks;

use App\Actions\Accounting\Payment\StorePayment;
use App\Actions\Accounting\WithCheckoutCom;
use App\Actions\IrisAction;
use App\Actions\Ordering\Order\SubmitOrder;
use App\Actions\Retina\Dropshipping\Orders\WithRetinaOrderPlacedRedirection;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Models\Accounting\OrderPaymentApiPoint;
use App\Models\Accounting\PaymentAccountShop;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class CheckoutComOrderPaymentSuccess extends IrisAction
{
    use AsAction;
    use WithCheckoutCom;
    use WithRetinaOrderPlacedRedirection;


    /**
     * @throws \Throwable
     */
    public function handle(OrderPaymentApiPoint $orderPaymentApiPoint, array $modelData): array
    {
        $paymentAccountShopID = Arr::get($orderPaymentApiPoint->data, 'payment_methods.checkout');
        $paymentAccountShop = PaymentAccountShop::find($paymentAccountShopID);

        $checkoutComPayment = $this->getCheckOutPayment(
            $paymentAccountShop,
            $modelData['cko-payment-id']
        );


        $amount = Arr::get($checkoutComPayment, 'amount', 0) / 100;

        $paymentData = [
            'reference'               => Arr::get($checkoutComPayment, 'id'),
            'amount'                  => $amount,
            'status'                  => PaymentStatusEnum::SUCCESS,
            'state'                   => PaymentStateEnum::COMPLETED,
            'type'                    => PaymentTypeEnum::PAYMENT,
            'payment_account_shop_id' => $paymentAccountShop->id,
            'api_point_type'          => class_basename($orderPaymentApiPoint),
            'api_point_id'            => $orderPaymentApiPoint->id,
        ];


        $order = DB::transaction(function () use ($orderPaymentApiPoint, $paymentAccountShop, $paymentData) {

            StorePayment::make()->action($orderPaymentApiPoint->order->customer, $paymentAccountShop->paymentAccount, $paymentData);

            $order = $orderPaymentApiPoint->order;



            return SubmitOrder::run($order);
        });

        return [
            'success' => true,
            'reason'  => 'Order paid successfully',
            'order'   => $order,
        ];


    }

    public function rules(): array
    {
        return [
            'cko-payment-session-id' => ['sometimes', 'string'],
            'cko-session-id'         => ['sometimes', 'string'],
            'cko-payment-id'         => ['sometimes', 'string'],
        ];
    }

    public function asController(OrderPaymentApiPoint $orderPaymentApiPoint, ActionRequest $request): array
    {
        $this->initialisation($request);
        return $this->handle($orderPaymentApiPoint, $this->validatedData);
    }

}
