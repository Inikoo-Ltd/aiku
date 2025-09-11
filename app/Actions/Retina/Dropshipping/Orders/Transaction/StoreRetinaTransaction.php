<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Fri, 09 May 2025 13:37:13 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Orders\Transaction;

use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\RetinaAction;
use App\Models\Catalogue\HistoricAsset;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

class StoreRetinaTransaction extends RetinaAction
{
    public function handle(Order $order, array $modelData): Transaction
    {
        $historicAssetId = $modelData['historic_asset_id'];

        $existingTransaction = $order->transactions()->where('historic_asset_id', $historicAssetId)->first();
        if ($existingTransaction) {
            throw ValidationException::withMessages(
                [
                        'message' => [
                            'amount' => 'Item already exist in basket',
                        ]
                    ]
            );
        }

        $historicAsset = HistoricAsset::find($historicAssetId);

        $transaction =  StoreTransaction::make()->action($order, $historicAsset, [
            'quantity_ordered' => Arr::get($modelData, 'quantity')
        ]);

        
        // Luigi: emit event 'add_to_cart' basket
        $gtm = [
            'ecommerce' => [
                'transaction_id'    => $order->id,
                'value'             => (float) $order->total_amount,
                'currency'          => $order->shop->currency->code,
                'items'             => [
                    [
                        'item_id' => $historicAsset?->model?->getLuigiIdentity()
                    ]
                ]
            ]
        ];
        request()->session()->flash('gtm', [
            'key'               => 'retina_dropshipping_add_to_cart',
            'event'             => 'add_to_cart',
            'data_to_submit'    => $gtm
        ]);
        
        return $transaction;
    }

    public function rules(): array
    {
        return [
            'quantity'          => ['required', 'numeric', 'min:0'],
            'historic_asset_id' => ['required', Rule::exists('historic_assets', 'id')],
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        $order = $request->route('order');
        if ($order->customer_id !== $this->customer->id) {
            return false;
        }
        return true;
    }

    public function asController(Order $order, ActionRequest $request): Transaction
    {
        $this->initialisation($request);

        return $this->handle($order, $this->validatedData);
    }

    public function htmlResponse(): RedirectResponse
    {
        return back();
    }
}
