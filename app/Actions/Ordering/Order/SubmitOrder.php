<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:11 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\Comms\Email\SendNewOrderEmailToCustomer;
use App\Actions\Comms\Email\SendNewOrderEmailToSubscribers;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateBasket;
use App\Actions\CRM\Customer\Hydrators\CustomerHydrateTrafficSource;
use App\Actions\Dropshipping\CustomerClient\Hydrators\CustomerClientHydrateBasket;
use App\Actions\Dropshipping\CustomerSalesChannel\Hydrators\CustomerSalesChannelsHydrateOrders;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Ordering\WithOrderingEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Order\OrderPayStatusEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Order\OrderStatusEnum;
use App\Enums\Ordering\Order\OrderToBePaidByEnum;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Enums\Ordering\Transaction\TransactionStatusEnum;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class SubmitOrder extends OrgAction
{
    use WithActionUpdate;
    use HasOrderHydrators;
    use WithOrderingEditAuthorisation;


    private Order $order;


    /**
     * @throws \Throwable
     */
    public function handle(Order $order): Order
    {
        $modelData = [
            'state'  => OrderStateEnum::SUBMITTED,
            'status' => OrderStatusEnum::PROCESSING,
        ];

        $date = now();

        if ($order->state == OrderStateEnum::CREATING || $order->submitted_at == null) {
            data_set($modelData, 'submitted_at', $date);
        }

        $transactions = $order->transactions()->where('state', TransactionStateEnum::CREATING)->get();
        /** @var Transaction $transaction */
        if ($transactions->isNotEmpty()) {
            foreach ($transactions as $transaction) {
                $transactionData = ['state' => TransactionStateEnum::SUBMITTED];
                if ($transaction->submitted_at == null) {
                    data_set($transactionData, 'submitted_at', $date);
                    data_set($transactionData, 'status', TransactionStatusEnum::PROCESSING);
                    data_set($transactionData, 'submitted_quantity_ordered', $transaction->quantity_ordered); //Copy quantity
                }

                $transaction->update($transactionData);
            }
        }

        $this->update($order, $modelData);

        if ($order->shop->masterShop) {
            $order->shop->masterShop->orderingStats->update(
                [
                    'last_order_submitted_at' => now()
                ]
            );
        }


        if ($order->customer_client_id) {
            CustomerClientHydrateBasket::run($order->customerClient);
        } else {
            CustomerHydrateBasket::run($order->customer);
        }

        $this->orderHydrators($order);
        SendNewOrderEmailToSubscribers::dispatch($order->id);
        SendNewOrderEmailToCustomer::dispatch($order->id);

        if ($order->pay_status == OrderPayStatusEnum::PAID || $order->to_be_paid_by == OrderToBePaidByEnum::CASH_ON_DELIVERY) {
            SendOrderToWarehouse::make()->action($order, []);
        }

        $customerSalesChannel = $order->customerSalesChannel;
        if ($customerSalesChannel) {
            CustomerSalesChannelsHydrateOrders::dispatch($customerSalesChannel);
        }

        CustomerHydrateTrafficSource::dispatch($order->customer);

        return $order;
    }


    public function afterValidator(Validator $validator): void
    {
        if ($this->order->state == OrderStateEnum::CREATING && !$this->order->transactions->count() && !$this->asAction) {
            $validator->errors()->add('state', __('Can not submit an order without any transactions'));
        } elseif ($this->order->state == OrderStateEnum::SUBMITTED) {
            $validator->errors()->add('state', __('Order is already submitted'));
        } elseif ($this->order->state == OrderStateEnum::PACKED || $this->order->state == OrderStateEnum::HANDLING) {
            $validator->errors()->add('state', __('Order is already been picked'));
        } elseif ($this->order->state == OrderStateEnum::FINALISED) {
            $validator->errors()->add('state', __('Order is already finalised'));
        } elseif ($this->order->state == OrderStateEnum::DISPATCHED) {
            $validator->errors()->add('state', __('Order is already dispatched'));
        } elseif ($this->order->state == OrderStateEnum::CANCELLED) {
            $validator->errors()->add('state', __('Order has been cancelled'));
        }
    }

    /**
     * @throws \Throwable
     */
    public function action(Order $order): Order
    {
        $this->asAction = true;
        $this->order    = $order;
        $this->initialisationFromShop($order->shop, []);

        return $this->handle($order);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Order $order, ActionRequest $request): Order
    {
        $this->order = $order;
        $this->initialisationFromShop($order->shop, $request);

        return $this->handle($order);
    }
}
