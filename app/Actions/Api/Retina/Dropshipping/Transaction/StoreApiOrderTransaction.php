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

class StoreApiOrderTransaction extends RetinaApiAction
{
    use AsAction;
    use WithAttributes;

    public function handle(Order $order, Portfolio $portfolio, array $modelData): Transaction|JsonResponse
    {
        if ($order->state != OrderStateEnum::CREATING) {
            return response()->json([
                'message' => 'This order is already in the "' . $order->state->value . '" state and cannot be updated.',
            ]);
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

    public function jsonResponse(Transaction $transaction)
    {
        return TransactionApiResource::make($transaction)
            ->additional([
                'message' => __('Transaction created successfully'),
            ]);
    }
}
