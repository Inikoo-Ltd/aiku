<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 12 Sept 2025 11:57:49 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Ordering;

use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\RetinaAction;
use App\Models\Catalogue\HistoricAsset;
use App\Models\Catalogue\Product;
use App\Models\Ordering\Order;
use App\Models\Ordering\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Lorisleiva\Actions\ActionRequest;

class StoreRetinaTransaction extends RetinaAction
{
    public function handle(Order $order, array $modelData): Transaction
    {
        $historicAssetId = $modelData['historic_asset_id'];

        $existingTransaction = $order->transactions()->where('historic_asset_id', $historicAssetId)->first();

        if ($existingTransaction) {
            return UpdateRetinaTransaction::run(
                $existingTransaction,
                [
                    'quantity_ordered' => Arr::get($modelData, 'quantity')
                ]
            );
        }

        $historicAsset = HistoricAsset::find($historicAssetId);

        $transaction = StoreTransaction::make()->action($order, $historicAsset, [
            'quantity_ordered' => Arr::get($modelData, 'quantity')
        ]);


        /** @var Product $product */
        $product = $historicAsset->model;
        // Luigi: emit event 'add_to_cart' basket
        $gtm = [
            'ecommerce' => [
                'transaction_id' => $order->id,
                'value'          => (float)$order->total_amount,
                'currency'       => $order->shop->currency->code,
                'items'          => [
                    [
                        'item_id' => $product->getLuigiIdentity()
                    ]
                ]
            ]
        ];
        request()->session()->flash('gtm', [
            'key'            => 'retina_add_to_cart',
            'event'          => 'add_to_cart',
            'data_to_submit' => $gtm
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

    public function prepareForValidation(ActionRequest $request): void
    {
        if ($request->has('product_id')) {
            $product = Product::find($request->get('product_id'));
            $request->merge(
                [
                    'historic_asset_id' => $product->currentHistoricProduct->id,

                ]
            );
        }
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
