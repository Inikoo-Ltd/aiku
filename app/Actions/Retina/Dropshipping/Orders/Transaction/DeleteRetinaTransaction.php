<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Fri, 09 May 2025 13:37:13 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Orders\Transaction;

use App\Actions\Ordering\Transaction\DeleteTransaction;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class DeleteRetinaTransaction extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    private Order $order;

    public function handle(Order $order, Transaction $transaction): Order
    {
        DeleteTransaction::make()->action($transaction);

        return $order;
    }

    public function authorize(ActionRequest $request): bool
    {
        return true;
    }

    public function prepareForValidation()
    {
        if ($this->order->state != OrderStateEnum::CREATING) {
            throw ValidationException::withMessages([
                'messages' => __('This order has been submitted and cannot be updated'),
            ]);
        }
    }

    public function asController(Order $order, Transaction $transaction, ActionRequest $request): Order
    {
        $this->order = $order;
        $this->initialisation($request);

        return $this->handle($order, $transaction);
    }
}
