<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 30-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Ecom\Basket;

use App\Actions\Ordering\Transaction\DeleteTransaction;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class RetinaDeleteBasketTransaction extends RetinaAction
{
    use WithActionUpdate;

    private Order $order;

    public function authorize(ActionRequest $request): bool
    {
        /** @var Transaction $transaction */
        $transaction = $request->route('transaction');
        if ($transaction->customer_id != $request->user()->customer_id) {
            return false;
        }

        return true;
    }

    public function handle(Transaction $transaction): Transaction
    {
        return DeleteTransaction::run($transaction);
    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function prepareForValidation(): void
    {
        if ($this->order->state != OrderStateEnum::CREATING) {
            throw ValidationException::withMessages([
                'messages' => __('This order has been submitted and cannot be updated'),
            ]);
        }
    }

    public function asController(Transaction $transaction, ActionRequest $request): Transaction
    {

        $this->order = $transaction->order;
        $this->initialisation($request);

        return $this->handle($transaction);
    }
}
