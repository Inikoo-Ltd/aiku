<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:12 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\Ordering\Order\Hydrators\OrderHydrateTransactions;
use App\Actions\Ordering\Transaction\UpdateTransaction;
use App\Actions\OrgAction;
use App\Actions\Traits\Rules\WithNoStrictRules;
use App\Actions\Traits\WithActionUpdate;
use App\Actions\Traits\WithFixedAddressActions;
use App\Actions\Traits\WithModelAddressActions;
use App\Actions\Traits\WithOrderExchanges;
use App\Enums\Ordering\Order\OrderShippingEngineEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Order\OrderStatusEnum;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Enums\Ordering\Transaction\TransactionStatusEnum;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class UpdateOrderShippingEngineAsManual extends OrgAction
{
    use WithActionUpdate;
    use WithFixedAddressActions;
    use WithModelAddressActions;
    use HasOrderHydrators;
    use WithNoStrictRules;
    use WithOrderExchanges;

    private Order $order;

    public function handle(Order $order, array $modelData): Order
    {

        $shippingTransaction = $order->transactions()->where('model_type', 'ShippingZone')->first();
        if ($shippingTransaction) {
            $this->updateShippingTransaction($shippingTransaction, Arr::get($modelData, 'shipping_amount'));
        } else {
            $this->storeShippingTransaction($order, Arr::get($modelData, 'shipping_amount'));
        }

        $order->update(
            [
                'shipping_engine' => OrderShippingEngineEnum::MANUAL,
                'shipping_amount' => Arr::get($modelData, 'shipping_amount'),
            ]
        );




        CalculateOrderTotalAmounts::run($order);

        return $order;

    }

    private function storeShippingTransaction(Order $order, $shippingAmount): Transaction
    {

        $modelData = [];
        data_set($modelData, 'tax_category_id', $order->tax_category_id);
        data_set($modelData, 'model_type', 'ShippingZone');


        $net   = $shippingAmount;
        $gross = $shippingAmount;




        data_set($modelData, 'shop_id', $order->shop_id);
        data_set($modelData, 'customer_id', $order->customer_id);
        data_set($modelData, 'group_id', $order->group_id);
        data_set($modelData, 'organisation_id', $order->organisation_id);

        data_set($modelData, 'date', now(), overwrite: false);
        data_set($modelData, 'submitted_at', $order->submitted_at);
        data_set($modelData, 'gross_amount', $gross ?? 0);
        data_set($modelData, 'net_amount', $net ?? 0);

        $status = match ($order->status) {
            OrderStatusEnum::CREATING => TransactionStatusEnum::CREATING,
            OrderStatusEnum::PROCESSING => TransactionStatusEnum::PROCESSING,
            default => TransactionStatusEnum::SETTLED,
        };

        $state = match ($order->state) {
            OrderStateEnum::SUBMITTED => TransactionStateEnum::SUBMITTED,
            OrderStateEnum::IN_WAREHOUSE => TransactionStateEnum::IN_WAREHOUSE,
            OrderStateEnum::HANDLING => TransactionStateEnum::HANDLING,
            OrderStateEnum::PACKED => TransactionStateEnum::PACKED,
            OrderStateEnum::FINALISED => TransactionStateEnum::FINALISED,
            OrderStateEnum::DISPATCHED => TransactionStateEnum::DISPATCHED,
            OrderStateEnum::CANCELLED => TransactionStateEnum::CANCELLED,

            default => TransactionStateEnum::CREATING,
        };


        data_set($modelData, 'state', $state);
        data_set($modelData, 'status', $status);


        if ($order->state == OrderStateEnum::SUBMITTED) {
            data_set($modelData, 'submitted_at', now(), overwrite: false);
        }



        $modelData = $this->processExchanges($modelData, $order->shop);


        /** @var Transaction $transaction */
        $transaction = $order->transactions()->create($modelData);
        OrderHydrateTransactions::dispatch($order);
        return $transaction;


    }


    private function updateShippingTransaction(Transaction $transaction, $shippingAmount): Transaction
    {
        return UpdateTransaction::run(
            $transaction,
            [
                'model_id'          => null,
                'asset_id'          => null,
                'historic_asset_id' => null,
                'gross_amount'      => $shippingAmount ?? 0,
                'net_amount'        => $shippingAmount ?? 0,
            ],
            false
        );
    }

    public function rules(): array
    {
        return [
                'shipping_amount' => ['required', 'numeric', 'min:0'],
        ];


    }


    public function asController(Order $order, ActionRequest $request): Order
    {
        $this->order = $order;
        $this->initialisationFromShop($order->shop, $request);

        return $this->handle($order, $this->validatedData);
    }
}
