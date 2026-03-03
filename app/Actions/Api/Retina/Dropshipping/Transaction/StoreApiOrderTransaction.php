<?php

/*
 * author Arya Permana - Kirin
 * created on 13-05-2025-11h-02m
 * github: https://github.com/KirinZero0
 * copyright 2025
*/

namespace App\Actions\Api\Retina\Dropshipping\Transaction;

use App\Actions\Api\Retina\Dropshipping\Resource\TransactionApiResource;
use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\RetinaApiAction;
use App\Enums\Ordering\Order\OrderStateEnum;
use App\Models\Dropshipping\Portfolio;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Illuminate\Support\Facades\Log;

class StoreApiOrderTransaction extends RetinaApiAction
{
    use AsAction;
    use WithAttributes;

    public function handle(Order $order, Portfolio $portfolio, array $modelData): Transaction|JsonResponse
    {
        if ($order->customer_id != $this->customer->id || $order->shop_id != $this->shop->id) {
            return response()->json([
                'message' => "Unable to make modifications for this order"
            ], 403);
        }

        if ($order->state != OrderStateEnum::CREATING) {
            return response()->json([
                'message' => "This order is already in the '{$order->state->value}' state and cannot be updated."
            ], 409);
        }

        if ($portfolio->customer_id != $this->customer->id) {
            return response()->json([
                'message' => "Unable to find related portfolio item"
            ], 404);
        }

        if($order->itemTransactions()->where('model_id', $portfolio->item_id)->exists()) {
            return response()->json([
                'message' => "Unable to create transaction for under this order. Another transaction with same the same product already exists.",
            ], 409);
        }

        return StoreTransaction::make()->action($order, $portfolio->item->historicAsset, $modelData);

    }

    public function rules(): array
    {
        return [
            'quantity_ordered'    => ['required', 'numeric', 'min:0'],
        ];

    }

    public function asController(Order $order, Portfolio $portfolio, ActionRequest $request): Transaction|JsonResponse
    {
        $this->initialisationFromDropshipping($request);

        return $this->handle($order, $portfolio, $this->validatedData);
    }

    public function jsonResponse(Transaction|JsonResponse $result)
    {
        if($result instanceof JsonResponse) return $result;
        
        return TransactionApiResource::make($result)
            ->additional([
                'message' => __('Transaction created successfully'),
            ]);
    }
}
