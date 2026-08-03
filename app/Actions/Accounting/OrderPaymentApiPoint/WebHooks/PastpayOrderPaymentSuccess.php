<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 17 Jul 2026 15:18:59 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Accounting\OrderPaymentApiPoint\WebHooks;

use App\Actions\Accounting\OrderPaymentApiPoint\UpdateOrderPaymentApiPoint;
use App\Actions\Accounting\Payment\PastPay\WithPastpayConfiguration;
use App\Actions\Accounting\Payment\StorePayment;
use App\Actions\IrisAction;
use App\Actions\Ordering\Order\AttachPaymentToOrder;
use App\Actions\Ordering\Order\UpdateState\SubmitOrder;
use App\Actions\Ordering\Transaction\Traits\WithChargeTransactions;
use App\Actions\Retina\Dropshipping\Orders\SettleRetinaOrderWithBalance;
use App\Actions\Retina\Dropshipping\Orders\WithRetinaOrderPlacedRedirection;
use App\Enums\Accounting\OrderPaymentApiPoint\OrderPaymentApiPointStateEnum;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Enums\Catalogue\Charge\ChargeStateEnum;
use App\Enums\Catalogue\Charge\ChargeTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Accounting\OrderPaymentApiPoint;
use App\Models\Accounting\Payment;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Billables\Charge;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Lorisleiva\Actions\ActionRequest;
use Sentry;

class PastpayOrderPaymentSuccess extends IrisAction
{
    use WithPastpayConfiguration;
    use WithChargeTransactions;
    use WithRetinaOrderPlacedRedirection {
        htmlResponse as orderPlacedResponse;
    }

    public const array PASTPAY_APPROVED_STATUSES = ['reserved', 'invoice_submitted', 'paid_out', 'paid_back'];

    /**
     * @throws \Throwable
     */
    public function handle(OrderPaymentApiPoint $orderPaymentApiPoint): array
    {
        $order = $orderPaymentApiPoint->order;

        if ($orderPaymentApiPoint->state == OrderPaymentApiPointStateEnum::SUCCESS) {
            return [
                'status'   => 'success',
                'success'  => true,
                'reason'   => 'Order paid successfully',
                'order'    => $order,
                'order_id' => $order->id,
            ];
        }

        $paymentAccountShop = PaymentAccountShop::find(
            Arr::get($orderPaymentApiPoint->data, 'pastpay.payment_account_shop_id')
        );

        if (!$paymentAccountShop) {
            return [
                'status'   => 'error',
                'success'  => false,
                'reason'   => __('PastPay payment was not initiated for this order.'),
                'order'    => $order,
                'order_id' => $order->id,
            ];
        }

        $this->paymentAccount = $paymentAccountShop->paymentAccount;

        try {
            $pastpayOrder = $this->pastpayGetOrder($order);
        } catch (\Exception $e) {
            Sentry::captureException($e);

            return [
                'status'   => 'error',
                'success'  => false,
                'reason'   => __('We could not verify your PastPay payment. Please try again.'),
                'order'    => $order,
                'order_id' => $order->id,
            ];
        }

        $status = Arr::get($pastpayOrder, 'data.status');

        if (!in_array($status, self::PASTPAY_APPROVED_STATUSES)) {
            PastpayOrderPaymentFailure::make()->processFailure($orderPaymentApiPoint, $pastpayOrder);

            return [
                'status'   => 'failure',
                'success'  => false,
                'reason'   => __('Your PastPay payment was not approved.'),
                'order'    => $order,
                'order_id' => $order->id,
            ];
        }

        return $this->processApprovedOrder($orderPaymentApiPoint, $paymentAccountShop, $pastpayOrder);
    }

    /**
     * @throws \Throwable
     */
    public function processApprovedOrder(OrderPaymentApiPoint $orderPaymentApiPoint, PaymentAccountShop $paymentAccountShop, array $pastpayOrder): array
    {
        $amount = Arr::get(
            $pastpayOrder,
            'data.totalPrice.amount',
            Arr::get($orderPaymentApiPoint->data, 'pastpay.to_pay')
        );

        $paymentData = [
            'reference'               => $orderPaymentApiPoint->order->reference,
            'amount'                  => $amount,
            'status'                  => PaymentStatusEnum::SUCCESS,
            'state'                   => PaymentStateEnum::COMPLETED,
            'type'                    => PaymentTypeEnum::PAYMENT,
            'payment_account_shop_id' => $paymentAccountShop->id,
            'api_point_type'          => class_basename($orderPaymentApiPoint),
            'api_point_id'            => $orderPaymentApiPoint->id,
            'data'                    => [
                'pastpay' => Arr::get($pastpayOrder, 'data', []),
            ],
        ];

        $order = DB::transaction(function () use ($orderPaymentApiPoint, $paymentAccountShop, $paymentData) {
            /** @var OrderPaymentApiPoint $orderPaymentApiPoint locked to stop the redirect and a concurrent retry processing the same payment twice */
            $orderPaymentApiPoint = OrderPaymentApiPoint::lockForUpdate()->find($orderPaymentApiPoint->id);

            $paymentAlreadyStored = Payment::where('payment_account_shop_id', $paymentAccountShop->id)
                ->where('reference', $paymentData['reference'])
                ->exists();

            if ($paymentAlreadyStored) {
                return $orderPaymentApiPoint->order;
            }

            $order = $orderPaymentApiPoint->order;

            $payment = StorePayment::make()->action($order->customer, $paymentAccountShop->paymentAccount, $paymentData);

            $chargeAmount = Arr::get($orderPaymentApiPoint->data, 'pastpay.charges', 0);
            $termDays     = Arr::get($orderPaymentApiPoint->data, 'pastpay.term_days');

            /** @var Charge $charge */
            $charge = $order->shop->charges()->where('type', ChargeTypeEnum::PAYMENT)
                ->where('code', 'like', 'Pastpay-'.$termDays.'-%')
                ->where('state', ChargeStateEnum::ACTIVE)->first();

            if ($charge && $chargeAmount > 0) {
                $netChargeAmount = round($chargeAmount / (1 + (float) $order->taxCategory->rate), 2);
                $this->storeChargeTransaction($order, $charge, $netChargeAmount);
            }

            AttachPaymentToOrder::make()->action($order, $payment, [
                'amount' => $payment->amount
            ]);

            $order->update(['is_pastpay' => true]);

            if ($order->total_amount > $order->payment_amount && $order->customer->balance > 0) {
                SettleRetinaOrderWithBalance::run($order);
            }

            UpdateOrderPaymentApiPoint::run(
                $orderPaymentApiPoint,
                [
                    'state'        => OrderPaymentApiPointStateEnum::SUCCESS,
                    'processed_at' => now(),
                    'data'         => [
                        'payment_id' => $payment->id,
                    ]
                ]
            );

            $order->refresh();

            if ($order->state == OrderStateEnum::CREATING) {
                $order = SubmitOrder::run($order);
            } elseif ($order->state == OrderStateEnum::CANCELLED) {
                Sentry::captureMessage(
                    'Approved PastPay payment '.$payment->reference.' recorded on cancelled order '.$order->id.' — cancellation in PastPay may be needed'
                );
            }

            return $order;
        });

        return [
            'status'   => 'success',
            'success'  => true,
            'reason'   => 'Order paid successfully',
            'order'    => $order,
            'order_id' => $order->id,
        ];
    }

    public function htmlResponse(array $arr): RedirectResponse
    {
        if (Arr::get($arr, 'success')) {
            return $this->orderPlacedResponse($arr);
        }

        return Redirect::route('retina.ecom.checkout.show')->with('modal', [
            'status'  => Arr::get($arr, 'status'),
            'title'   => __('Payment not completed'),
            'message' => Arr::get($arr, 'reason'),
        ]);
    }

    /**
     * @throws \Throwable
     */
    public function asController(OrderPaymentApiPoint $orderPaymentApiPoint, ActionRequest $request): array
    {
        $this->initialisation($request);

        return $this->handle($orderPaymentApiPoint);
    }
}
