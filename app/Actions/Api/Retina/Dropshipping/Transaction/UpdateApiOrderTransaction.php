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
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Ordering\Transaction;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class UpdateApiOrderTransaction extends RetinaApiAction
{
    use AsAction;
    use WithAttributes;

    public function handle(Transaction $transaction, array $modelData): Transaction|JsonResponse
    {
        if ($transaction->order->state != OrderStateEnum::CREATING) {
            return response()->json([
                'message' => 'This order is already in the "' . $transaction->order->state->value . '" state and cannot be updated.',
            ]);
        }
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

    public function asController(Transaction $transaction, ActionRequest $request): Transaction|JsonResponse
    {
        $this->initialisationFromDropshipping($request);

        return $this->handle($transaction, $this->validatedData);
    }
}
