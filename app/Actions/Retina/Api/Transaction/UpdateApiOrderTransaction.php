<?php
/*
 * author Arya Permana - Kirin
 * created on 13-05-2025-13h-08m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Retina\Api\Transaction;

use App\Actions\Ordering\Transaction\UpdateTransaction;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class UpdateApiOrderTransaction
{
    use AsAction;
    use WithAttributes;

    public function handle(Transaction $transaction, array $modelData): Transaction
    {
        return UpdateTransaction::make()->action($transaction, $modelData);
    }

    public function authorize(ActionRequest $request): bool
    {
        return true;
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
        return [
            'transaction' => $transaction, //todo: transaction resource
        ];
    }

    public function asController(Transaction $transaction, ActionRequest $request): Transaction
    {
        $this->fillFromRequest($request);
        $validatedData = $this->validateAttributes();

        return $this->handle($transaction, $validatedData);
    }
}
