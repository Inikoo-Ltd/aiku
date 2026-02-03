<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:12 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order\UpdateState;

use App\Actions\Ordering\Order\CalculateOrderTotalAmounts;
use App\Actions\Ordering\Order\GenerateInvoiceFromOrder;
use App\Actions\Ordering\Order\HasOrderHydrators;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Enums\Ordering\Transaction\TransactionStatusEnum;
use App\Models\Ordering\Order;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class UpdateOrderStateToPacked extends OrgAction
{
    use WithActionUpdate;
    use HasOrderHydrators;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(Order $order, bool $fromDeliveryNote = false): Order
    {
        $oldState = $order->state;
        $data     = [
            'state' => OrderStateEnum::PACKED
        ];

        if (in_array($order->state, [
                OrderStateEnum::HANDLING,
                OrderStateEnum::FINALISED,
                OrderStateEnum::IN_WAREHOUSE,
            ])
            || $fromDeliveryNote) {
            foreach ($order->transactions()->where('model_type', 'Product')->get() as $transaction) {
                $packedData = GenerateInvoiceFromOrder::make()->recalculateTransactionTotals($transaction);
                $transaction->update(
                    [
                        'state'           => TransactionStateEnum::PACKED,
                        'status'          => TransactionStatusEnum::PROCESSING,
                        'quantity_picked' => $packedData['quantity'],
                        'gross_amount'    => $packedData['gross_amount'],
                        'net_amount'      => $packedData['net_amount'],
                        'org_net_amount'  => $packedData['org_net_amount'],
                        'grp_net_amount'  => $packedData['grp_net_amount'],
                    ]
                );
            }


            $data['packed_at'] = now();

            $this->update($order, $data);

            CalculateOrderTotalAmounts::run($order);
            $order->refresh();

            $this->orderHydrators($order);
            $this->orderHandlingHydrators($order, $oldState);
            $this->orderHandlingHydrators($order, OrderStateEnum::PACKED);

            return $order;
        }

        throw ValidationException::withMessages(['status' => 'Error, order state is '.$order->state->value]);
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function action(Order $order, bool $fromDeliveryNote): Order
    {
        $this->asAction = true;
        $this->initialisationFromShop($order->shop, []);

        return $this->handle($order, $fromDeliveryNote);
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function asController(Order $order, ActionRequest $request): Order
    {
        $this->initialisationFromShop($order->shop, $request);

        return $this->handle($order);
    }
}
