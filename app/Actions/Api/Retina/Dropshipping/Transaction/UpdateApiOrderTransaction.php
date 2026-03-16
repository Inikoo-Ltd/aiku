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
use App\Models\Catalogue\Product;
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
        $order = $transaction->order;

        if ($transaction->customer_id != $this->customer->id || $order->shop_id != $this->shop->id) {
            return response()->json([
                'message' => "Unable to make modifications for this order",
            ], 403);
        }

        if ($transaction->order->state != OrderStateEnum::CREATING) {
            return response()->json([
                'message' => "This order is already in the '{$order->state->value}' state and cannot be updated.",
            ], 409);
        }

        if ($transaction->model_type != class_basename(Product::class)) {
            return response()->json([
                'message' => 'Unable to modify this transaction data. Only able to modify product transaction data.',
            ], 422);
        }

        return UpdateTransaction::make()->action($transaction, $modelData);
    }

    public function rules(): array
    {
        return [
            'quantity_ordered' => ['sometimes', 'numeric', 'min:0']
        ];
    }

    public function jsonResponse(Transaction|JsonResponse $result)
    {
        if ($result instanceof JsonResponse) {
            return $result;
        }

        return TransactionApiResource::make($result)
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
