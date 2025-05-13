<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:11 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\Dispatching\DeliveryNote\Hydrators\DeliveryNoteHydrateDeliveryNoteItemsSalesType;
use App\Actions\Dispatching\DeliveryNote\StoreDeliveryNote;
use App\Actions\Dispatching\DeliveryNoteItem\StoreDeliveryNoteItem;
use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\Ordering\WithOrderingEditAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Dispatching\DeliveryNote\DeliveryNoteStateEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Models\Catalogue\Product;
use App\Models\Dispatching\DeliveryNote;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class SendOrderToWarehouse extends OrgAction
{
    use WithActionUpdate;
    use HasOrderHydrators;
    use WithOrderingEditAuthorisation;


    private Order $order;


    /**
     * @throws \Throwable
     */
    public function handle(Order $order, array $modelData): DeliveryNote
    {
        data_set($modelData, 'state', OrderStateEnum::IN_WAREHOUSE);
        $date = now();


        $warehouseId = Arr::pull($modelData, 'warehouse_id');
        data_forget($modelData, 'warehouse_id');

        if ($order->state == OrderStateEnum::SUBMITTED || $order->in_warehouse_at == null) {
            data_set($modelData, 'in_warehouse_at', $date);
        }

        /** @var Transaction $transactions */
        $transactions = $order->transactions()->where('state', TransactionStateEnum::SUBMITTED)->get();
        foreach ($transactions as $transaction) {
            $transactionData = ['state' => TransactionStateEnum::IN_WAREHOUSE];
            if ($transaction->in_warehouse_at == null) {
                data_set($transactionData, 'in_warehouse_at', $date);
            }
            $transaction->update($transactionData);
        }


        $deliveryNoteData = [
            'delivery_address' => $order->deliveryAddress,
            'date'             => $date,
            'reference'        => $order->reference,
            'state'            => DeliveryNoteStateEnum::UNASSIGNED,
            'submitted_at'     => now(),
            'warehouse_id'     => $warehouseId
        ];

        $deliveryNote = StoreDeliveryNote::make()->action($order, $deliveryNoteData);

        $transactions = $order->transactions()->where('model_type', 'Product')->get();

        /** @var Transaction $transaction */
        foreach ($transactions as $transaction) {
            $product = Product::find($transaction->model_id);
            foreach ($product->orgStocks as $orgStock) {
                $quantity             = $orgStock->pivot->quantity * $transaction->quantity_ordered;
                $deliveryNoteItemData = [
                    'org_stock_id'      => $orgStock->id,
                    'transaction_id'    => $transaction->id,
                    'quantity_required' => $quantity
                ];
                StoreDeliveryNoteItem::make()->action($deliveryNote, $deliveryNoteItemData);
            }
        }

        DeliveryNoteHydrateDeliveryNoteItemsSalesType::run($deliveryNote);
        UpdateOrder::make()->action($order, $modelData);


        return $deliveryNote;
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => [
                'required',
                Rule::exists('warehouses', 'id')
                    ->where('organisation_id', $this->organisation->id),
            ],
        ];
    }

    public function prepareForValidation(): void
    {
        if (!$this->has('warehouse_id')) {
            $warehouse = $this->shop->organisation->warehouses()->first();
            $this->set('warehouse_id', $warehouse->id);
        }
    }

    public function afterValidator(Validator $validator): void
    {
        if ($this->order->state == OrderStateEnum::CREATING) {
            $validator->errors()->add('state', __('Only submitted orders can be send to warehouse'));
        } elseif ($this->order->state == OrderStateEnum::SUBMITTED && !$this->order->transactions->count()) {
            $validator->errors()->add('state', __('Order dont have any transactions to be send to warehouse'));
        } elseif ($this->order->state == OrderStateEnum::IN_WAREHOUSE || $this->order->state == OrderStateEnum::HANDLING || $this->order->state == OrderStateEnum::PACKED) {
            $validator->errors()->add('state', __('Order already in warehouse'));
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
    public function action(Order $order, array $modelData): DeliveryNote
    {
        $this->asAction = true;
        $this->order    = $order;
        $this->initialisationFromShop($order->shop, $modelData);

        return $this->handle($order, $this->validatedData);
    }

    /**
     * @throws \Throwable
     */
    public function asController(Order $order, ActionRequest $request): DeliveryNote
    {
        $this->order = $order;
        $this->initialisationFromShop($order->shop, $request);

        return $this->handle($order, $this->validatedData);
    }

}
