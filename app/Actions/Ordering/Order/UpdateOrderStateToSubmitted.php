<?php
/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:11 Malaysia Time, Pantai Lembeng, Bali, Id
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\OrgAction;
use App\Actions\Traits\Authorisations\HasOrderingAuthorisation;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Order\OrderStatusEnum;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Enums\Ordering\Transaction\TransactionStatusEnum;
use App\Models\Ordering\Order;
use Illuminate\Validation\Validator;
use Lorisleiva\Actions\ActionRequest;

class UpdateOrderStateToSubmitted extends OrgAction
{
    use WithActionUpdate;
    use HasOrderHydrators;
    use HasOrderingAuthorisation;


    private Order $order;

    public function __construct()
    {
        $this->authorisationType = 'update';
    }

    public function handle(Order $order): Order
    {
        $modelData = [
            'state' => OrderStateEnum::SUBMITTED,
            'status' => OrderStatusEnum::PROCESSING,
        ];

        $date = now();

        if ($order->state == OrderStateEnum::CREATING || $order->submitted_at == null) {
            data_set($modelData, 'submitted_at', $date);
        }

        $transactions = $order->transactions()->where('state', TransactionStateEnum::CREATING);
        foreach ($transactions as $transaction) {
            $transactionData = ['state' => TransactionStateEnum::SUBMITTED];
            if ($transaction->submitted_at == null) {
                data_set($transactionData, 'submitted_at', $date);
                data_set($transactionData, 'status', TransactionStatusEnum::PROCESSING);
            }

            $transaction->update($transactionData);
        }

        $this->update($order, $modelData);
        $this->orderHydrators($order);

        return $order;
    }


    public function afterValidator(Validator $validator): void
    {
        if ($this->order->state == OrderStateEnum::CREATING && !$this->order->transactions->count()) {
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

    public function action(Order $order): Order
    {
        $this->asAction = true;
        $this->scope    = $order->shop;
        $this->order    = $order;
        $this->initialisationFromShop($order->shop, []);

        return $this->handle($order);
    }

    public function asController(Order $order, ActionRequest $request)
    {
        $this->order = $order;
        $this->scope = $order->shop;
        $this->initialisationFromShop($order->shop, $request);
        return $this->handle($order);
    }
}
