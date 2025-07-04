<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 20 Jun 2023 20:33:12 Malaysia Time, Pantai Lembeng, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Ordering\Order;

use App\Actions\Accounting\Invoice\StoreInvoice;
use App\Actions\Accounting\InvoiceTransaction\StoreInvoiceTransaction;
use App\Actions\Accounting\InvoiceTransaction\StoreInvoiceTransactionFromAdjustment;
use App\Actions\Accounting\InvoiceTransaction\StoreInvoiceTransactionFromCharge;
use App\Actions\Accounting\InvoiceTransaction\StoreInvoiceTransactionFromShipping;
use App\Actions\OrgAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Accounting\Invoice\InvoiceTypeEnum;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Models\Ordering\Adjustment;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class FinaliseOrder extends OrgAction
{
    use WithActionUpdate;
    use HasOrderHydrators;

    /**
     * @throws \Illuminate\Validation\ValidationException
     * @throws \Throwable
     */
    public function handle(Order $order): Order
    {

        GenerateOrderInvoice::make()->action($order);

        $data = [
            'state' => OrderStateEnum::FINALISED
        ];

        if (in_array($order->state, [OrderStateEnum::HANDLING, OrderStateEnum::PACKED])) {
            $order->transactions()->update([
                'state' => TransactionStateEnum::FINALISED
            ]);

            $data['finalised_at'] = now();

            $this->update($order, $data);

            $this->orderHydrators($order);

            return $order;
        }

        throw ValidationException::withMessages(['status' => 'You can not change the status to finalized']);
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function action(Order $order): Order
    {
        return $this->handle($order);
    }

    public function asController(Order $order, ActionRequest $request) // Candidate for removal we will only folow DN finalisation
    {
        $this->order = $order;
        $this->initialisationFromShop($order->shop, $request);

        return $this->handle($order);
    }
}
