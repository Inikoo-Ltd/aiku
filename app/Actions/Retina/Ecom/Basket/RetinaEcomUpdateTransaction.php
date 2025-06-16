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
use App\Models\Ordering\Transaction;
use Lorisleiva\Actions\ActionRequest;

class RetinaEcomUpdateTransaction extends RetinaAction
{
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

    public function asController(Transaction $transaction, ActionRequest $request)
    {
        $this->initialisation($request);

        $this->handle($transaction, $this->validatedData);
    }
}
