<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 30-04-2025, Bali, Indonesia
 * GitHub: https://github.com/Ganes556
 * Copyright: 2025
 *
*/

namespace App\Actions\Retina\Ecom\Basket;

use App\Actions\Ordering\Transaction\UpdateTransaction;
use App\Actions\RetinaAction;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\CRM\Customer;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class RetinaEcomUpdateTransaction extends RetinaAction
{
    private Order $order;
    private Transaction $transaction;

    public function handle(Transaction $transaction, array $modelData)
    {
        $transaction->order->update([
            'updated_by_customer_at' => now()
        ]);
        return UpdateTransaction::run($transaction, $modelData);
    }


    public function authorize(ActionRequest $request): bool
    {
        /** @var Transaction $transaction */
        $transaction = $request->route('transaction');
        if ($transaction->customer_id != $request->user()->customer_id) {
            return false;
        }

        return true;
    }

    public function rules(): array
    {

        return [
                'quantity_ordered'    => ['sometimes', 'numeric', 'min:0'],
            ];

    }

    /**
     * @throws \Illuminate\Validation\ValidationException
     */
    public function prepareForValidation(ActionRequest $request): void
    {
        if ($this->order->state != OrderStateEnum::CREATING) {
            throw ValidationException::withMessages([
                'message' => __('This order has been submitted and cannot be updated'),
            ]);
        }
        if (isset($request['quantity_ordered'])) {
            $availableQuantity = $this->transaction->asset?->product?->available_quantity ?? PHP_INT_MAX;
            $this->set('quantity_ordered', min($request['quantity_ordered'], $availableQuantity));
        }
    }

    public function action(Transaction $transaction, Customer $customer, array $modelData): Transaction
    {
        $this->transaction = $transaction;
        $this->order       = $transaction->order;
        $this->initialisationActions($customer, $modelData);

        return $this->handle($transaction, $this->validatedData);
    }

    public function asController(Transaction $transaction, ActionRequest $request): void
    {
        $this->transaction = $transaction;
        $this->order       = $transaction->order;
        $this->initialisation($request);

        $this->handle($transaction, $this->validatedData);
    }
}
