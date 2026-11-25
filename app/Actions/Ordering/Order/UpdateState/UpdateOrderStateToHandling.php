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
use App\Models\Ordering\Transaction;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class UpdateOrderStateToHandling extends OrgAction
{
    use WithActionUpdate;
    use HasOrderHydrators;

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function handle(Order $order): Order
    {
        $oldState = $order->state;
        $data     = [
            'state' => OrderStateEnum::HANDLING
        ];

        if (in_array($order->state, [
            OrderStateEnum::SUBMITTED,
            OrderStateEnum::IN_WAREHOUSE,
            OrderStateEnum::HANDLING,
            // Resuming a blocked delivery note brings its order back here, see StartHandlingDeliveryNote
            OrderStateEnum::HANDLING_BLOCKED,
            OrderStateEnum::PACKED,
            OrderStateEnum::PICKED,
            OrderStateEnum::FINALISED,
        ])) {
            if ($oldState->isPickedOrLater()) {
                $transactions     = $order->transactions()->where('model_type', 'Product')->where('is_follow_on', false)->get();
                $submittedTotals  = GenerateInvoiceFromOrder::make()->recalculateTransactionsTotals($transactions, null);

                /** @var Transaction $transaction */
                foreach ($transactions as $transaction) {
                    $submittedAmounts = $submittedTotals[$transaction->id];

                    $transaction->update(
                        [
                            'quantity_picked' => null,
                            'gross_amount'    => $submittedAmounts['gross_amount'],
                            'net_amount'      => $submittedAmounts['net_amount'],
                            'org_net_amount'  => $submittedAmounts['org_net_amount'],
                            'grp_net_amount'  => $submittedAmounts['grp_net_amount'],
                        ]
                    );
                }
                CalculateOrderTotalAmounts::run(order: $order);
                $order->refresh();
            }


            $order->transactions()->update([
                'state'  => TransactionStateEnum::HANDLING,
                'status' => TransactionStatusEnum::PROCESSING
            ]);

            $data['handling_at'] = now();
            $data['packed_at']   = null;

            $this->update($order, $data);

            $this->orderHydrators($order);
            $this->orderHandlingHydrators($order, $oldState);
            $this->orderHandlingHydrators($order, OrderStateEnum::HANDLING);

            return $order;
        }

        throw ValidationException::withMessages(['status' => 'Can not change the status to handling (current status is '.$order->state->value.')']);
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function action(Order $order): Order
    {
        $this->asAction = true;
        $this->initialisationFromShop($order->shop, []);

        return $this->handle($order);
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
