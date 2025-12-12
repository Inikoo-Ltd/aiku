<?php

/*
 * Author: Ganes <gustiganes@gmail.com>
 * Created on: 30-04-2025, Bali, Indonesia
 * Github: https://github.com/Ganes556
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

    public function handle(Transaction $transaction, array $modelData)
    {
        return UpdateTransaction::run($transaction, $modelData);
    }


    public function authorize(ActionRequest $request): bool
    {
        return $request->user()->is_root;
    }

    public function rules(): array
    {

        return [
                'quantity_ordered'    => ['sometimes', 'numeric', 'min:0'],
            ];

    }

    public function prepareForValidation()
    {
        if ($this->order->state != OrderStateEnum::CREATING) {
            throw ValidationException::withMessages([
                'message' => __('This order has been submitted and cannot be updated'),
            ]);
        }
    }

    // TODO VIKA
    // public function action(Transaction $transaction, Customer $customer, array $modelData): Transaction
    // {
    //     $this->order = $transaction->order;
    //     $this->initialisationActions($customer, $modelData);

    //     return $this->handle($transaction, $this->validatedData);
    // }

    public function asController(Transaction $transaction, ActionRequest $request)
    {
        $this->order = $transaction->order;
        $this->initialisation($request);

        $this->handle($transaction, $this->validatedData);
    }
}
