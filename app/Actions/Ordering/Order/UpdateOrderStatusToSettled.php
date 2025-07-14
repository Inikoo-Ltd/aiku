<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:12 Malaysia Time, Pantai Lembeng, Bali, Id
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Order\OrderStatusEnum;
use App\Enums\Ordering\Transaction\TransactionStatusEnum;
use App\Models\Ordering\Order;
use Illuminate\Validation\ValidationException;

class UpdateOrderStatusToSettled
{
    use WithActionUpdate;
    use HasOrderHydrators;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(Order $order): Order
    {
        $data = [
            'status' => OrderStatusEnum::SETTLED
        ];

        if ($order->state === OrderStateEnum::FINALISED) {
            $transactions = $order->transactions()->where('status', TransactionStatusEnum::PROCESSING)->get();
            foreach ($transactions as $transaction) {
                data_set($transactionData, 'settled_at', now());
                data_set($transactionData, 'status', TransactionStatusEnum::SETTLED);

                $transaction->update($transactionData);
            }

            $data['settled_at']                 = now();

            $this->update($order, $data);

            $this->orderHydrators($order);

            return $order;
        }

        throw ValidationException::withMessages(['status' => 'You can not change the status to settled']);
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function action(Order $order): Order
    {
        return $this->handle($order);
    }
}
