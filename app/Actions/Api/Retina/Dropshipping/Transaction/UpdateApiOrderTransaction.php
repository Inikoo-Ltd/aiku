<?php

/*
 * author Arya Permana - Kirin
 * created on 13-05-2025-13h-08m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Api\Retina\Dropshipping\Transaction;

use App\Actions\Api\Retina\Dropshipping\Resource\TransactionApiResource;
use App\Actions\Ordering\Transaction\UpdateTransaction;
use App\Actions\RetinaApiAction;
use App\Models\Ordering\Transaction;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class UpdateApiOrderTransaction extends RetinaApiAction
{
    use AsAction;
    use WithAttributes;

    public function handle(Transaction $transaction, array $modelData): Transaction
    {
        return UpdateTransaction::make()->action($transaction, $modelData);
    }

    public function rules(): array
    {
        $rules = [
            'quantity_ordered'    => ['sometimes', 'numeric', 'min:0']
        ];

        return $rules;
    }

    public function jsonResponse(Transaction $transaction)
    {
        return TransactionApiResource::make($transaction)
            ->additional([
                'message' => __('Transaction updated successfully'),
            ]);
    }

    public function asController(Transaction $transaction, ActionRequest $request): Transaction
    {
        $this->initialisationFromDropshipping($request);

        return $this->handle($transaction, $this->validatedData);
    }
}
