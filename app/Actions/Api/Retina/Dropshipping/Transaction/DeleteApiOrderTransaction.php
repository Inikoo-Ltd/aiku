<?php

/*
 * author Arya Permana - Kirin
 * created on 13-05-2025-13h-30m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Api\Retina\Dropshipping\Transaction;

use App\Actions\Api\Retina\Dropshipping\Resource\TransactionApiResource;
use App\Actions\Ordering\Transaction\DeleteTransaction;
use App\Actions\RetinaApiAction;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Ordering\Transaction;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class DeleteApiOrderTransaction extends RetinaApiAction
{
    use AsAction;
    use WithAttributes;

    public function handle(Transaction $transaction): Transaction|JsonResponse
    {
        if($transaction->order->state != OrderStateEnum::CREATING) {
            return response()->json([
                'message' => 'This order is already in the "' . $transaction->order->state->value . '" state and cannot be updated.',
            ]);
        }
        return DeleteTransaction::make()->action($transaction);
    }

    public function authorize(ActionRequest $request): bool
    {
        return true;
    }

    public function jsonResponse(Transaction $transaction)
    {
        return TransactionApiResource::make($transaction)
            ->additional([
                'message' => __('Transaction deleted successfully'),
            ]);
    }

    public function asController(Transaction $transaction, ActionRequest $request): Transaction|JsonResponse
    {
        $this->initialisationFromDropshipping($request);
        return $this->handle($transaction);
    }
}
