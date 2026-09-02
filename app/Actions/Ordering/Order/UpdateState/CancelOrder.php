<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:12 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order\UpdateState;

use App\Actions\Accounting\CreditTransaction\StoreCreditTransaction;
use App\Actions\Accounting\Payment\StorePayment;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateBasket;
use App\Actions\CRM\TrafficSource\Hydrator\TrafficSourceHydrateCustomers;
use App\Actions\Dispatching\DeliveryNote\UpdateState\CancelDeliveryNote;
use App\Actions\Dropshipping\Allegro\Order\CancelFulfillOrderAllegro;
use App\Actions\Dropshipping\Shopify\Fulfilment\CloseFulfillOrderToShopify;
use App\Actions\Dropshipping\Tiktok\Order\CancelFulfillOrderTiktok;
use App\Actions\Ordering\Order\AttachPaymentToOrder;
use App\Actions\Ordering\Order\HasOrderHydrators;
use App\Actions\Ordering\Order\UpdateOrder;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Ordering\WithOrderingEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Accounting\CreditTransaction\CreditTransactionReasonEnum;
use App\Enums\Accounting\CreditTransaction\CreditTransactionTypeEnum;
use App\Enums\Accounting\Payment\PaymentStateEnum;
use App\Enums\Accounting\Payment\PaymentStatusEnum;
use App\Enums\Accounting\Payment\PaymentTypeEnum;
use App\Enums\Catalogue\Shop\ShopTypeEnum;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Ordering\Order\OrderCancellationReasonEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Platform\PlatformTypeEnum;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Models\Accounting\PaymentAccountShop;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class CancelOrder extends OrgAction
{
    use WithActionUpdate;
    use HasOrderHydrators;
    use WithOrderingEditAuthorisation;


    private Order $order;


    /**
     * @throws \Throwable
     */
    public function handle(Order $order, array $modelData = []): Order
    {
        $oldState = $order->state;

        $modelData = [
            'state' => OrderStateEnum::CANCELLED,
        ];

        $date = now();

        if ($order->cancelled_at == null) {
            data_set($modelData, 'cancelled_at', $date);
        }
        $this->update($order, $modelData);

        $transactions = $order->transactions()->where('state', TransactionStateEnum::CREATING)->get();

        /** @var Transaction $transaction */
        foreach ($transactions as $transaction) {
            $transactionData = [
                'state' => TransactionStateEnum::CANCELLED,
                'cancelled_at' => $date
            ];
            data_set($transactionData, 'quantity_cancelled', $transaction->quantity_ordered);

            $transaction->update($transactionData);
        }

        if ($order->payment_amount > 0) {
            StoreCreditTransaction::make()->action($order->customer, [
                'amount' => $order->payment_amount,
                'type'   => CreditTransactionTypeEnum::MONEY_BACK,
                'reason' => CreditTransactionReasonEnum::ORDER_CANCELLED,
                'notes'  => $this->getCreditTransactionNotes($order, $modelData),
            ]);


            $paymentAccountShop = PaymentAccountShop::where('shop_id', $order->shop_id)->where('type', 'account')->where('state', 'active')->first();

            $paymentData = [
                'reference'               => 'cu-'.$order->customer->id.'-return-bal-'.Str::random(10),
                'amount'                  => -$order->payment_amount,
                'status'                  => PaymentStatusEnum::SUCCESS,
                'payment_account_shop_id' => $paymentAccountShop->id,
                'state'                   => PaymentStateEnum::COMPLETED,
                'type'                    => PaymentTypeEnum::REFUND
            ];


            $payment = StorePayment::make()->action($order->customer, $paymentAccountShop->paymentAccount, $paymentData);

            AttachPaymentToOrder::make()->action($order, $payment, [
                'amount' => $payment->amount
            ]);
        }

        $deliveryNotes = $order->deliveryNotes;
        foreach ($deliveryNotes as $deliveryNote) {
            if ($deliveryNote->state != DeliveryNoteStateEnum::CANCELLED) {
                CancelDeliveryNote::make()->action($deliveryNote, null, false);
            }
        }

        if ($oldState == OrderStateEnum::CREATING) {
            CustomerHydrateBasket::run($order->customer_id);
        }

        if ($order->shop->type == ShopTypeEnum::DROPSHIPPING) {
            if ($order->customerSalesChannel?->user) {
                match ($order->customerSalesChannel->platform->type) {
                    PlatformTypeEnum::SHOPIFY => CloseFulfillOrderToShopify::run($order),
                    PlatformTypeEnum::TIKTOK => CancelFulfillOrderTiktok::run($order),
                    PlatformTypeEnum::ALLEGRO => CancelFulfillOrderAllegro::run($order),
                    default => null,
                };
            } elseif ($order->customerSalesChannel?->platform?->type !== PlatformTypeEnum::MANUAL) {
                UpdateOrder::run($order, [
                    'public_notes' => __('We\'re unable update order to customer\'s sales channel due to their sales channel are not found or already deleted.')
                ]);
            }
        }


        $this->orderHydrators($order);
        $this->orderHandlingHydrators($order, $oldState);
        $this->orderHandlingHydrators($order, OrderStateEnum::CANCELLED);

        $this->refreshTrafficSourceStats($order);

        return $order;
    }

    /**
     * TrafficSourceHydrateCustomers re-sums the orders and invoices of every customer a traffic source
     * is credited with, and cancelling this order changes this customer's totals, so every source
     * crediting either the order or the customer holds a stale figure until it is rehydrated.
     *
     * The pivot attribution rows themselves are preserved as an audit trail of what originally
     * acquired the order.
     *
     * ponytail: the extra delay keeps this from reading the customer's rollups before the order
     * hydrators above have written them. Chain it after CustomerHydrateOrderStats if queue depth ever
     * makes a fixed offset unreliable.
     */
    private function refreshTrafficSourceStats(Order $order): void
    {
        $trafficSources = $order->trafficSources
            ->merge($order->customer?->trafficSources ?? [])
            ->unique('id');

        foreach ($trafficSources as $trafficSource) {
            TrafficSourceHydrateCustomers::dispatch($trafficSource)->delay($this->hydratorsDelay + 120);
        }
    }

    /**
     * The note is what the customer reads in the credit balance notification email, so the
     * selected reason and any typed detail are spelled out there instead of leaving them
     * with the generic "money returned as store credit" line.
     */
    private function getCreditTransactionNotes(Order $order, array $modelData): string
    {
        $reason      = Arr::get($modelData, 'cancellation_reason');
        $reasonLabel = $reason instanceof OrderCancellationReasonEnum
            ? $reason->label()
            : OrderCancellationReasonEnum::tryFrom((string)$reason)?->label();

        $explanation = rtrim(
            collect([$reasonLabel, trim((string)Arr::get($modelData, 'cancellation_notes'))])
                ->filter()
                ->implode('. '),
            " \t\n."
        );

        if ($explanation === '') {
            return "Order #$order->reference cancelled. Money returned as store credit.";
        }

        return "Order #$order->reference cancelled: $explanation. Money returned as store credit.";
    }

    public function rules(): array
    {
        return [
            'cancellation_reason' => ['sometimes', 'nullable', Rule::enum(OrderCancellationReasonEnum::class)],
            'cancellation_notes'  => ['sometimes', 'nullable', 'string', 'max:4000'],
        ];
    }

    public function afterValidator(Validator $validator): void
    {
        $order = $this->order;
        if ($order->state === OrderStateEnum::CANCELLED) {
            $validator->errors()->add('messages', 'Order is already cancelled.');
        } elseif (in_array($order->state, [OrderStateEnum::DISPATCHED, OrderStateEnum::FINALISED])) {
            $validator->errors()->add('messages', "Cannot cancel an order in '{$order->state->value}' state.");
        } elseif ($order->invoices()->count() > 0) {
            $validator->errors()->add('messages', 'Cannot cancel an order with invoices. Please delete the invoices first.');
        }

        $deliveryNotes = $order->deliveryNotes()->get();
        if ($deliveryNotes->count() > 0) {
            /** @var \App\Models\Dispatching\DeliveryNote $deliveryNote */
            foreach ($deliveryNotes as $deliveryNote) {
                if ($deliveryNote->state === DeliveryNoteStateEnum::DISPATCHED) {
                    $validator->errors()->add('messages', 'Cannot cancel an order with dispatched delivery notes. Please cancel the delivery notes first.');
                }
            }
        }
    }

    public function action(Order $order, array $modelData = []): Order
    {
        $this->asAction = true;
        $this->order    = $order;
        $this->initialisationFromShop($order->shop, $modelData);

        return $this->handle($order, $this->validatedData);
    }

    public function asController(Order $order, ActionRequest $request): Order
    {
        $this->order = $order;
        $this->initialisationFromShop($order->shop, $request);

        return $this->handle($order, $this->validatedData);
    }
}
