<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Fri, 09 May 2025 13:37:13 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Retina\Dropshipping\Orders\Transaction;

use App\Actions\Ordering\Transaction\StoreTransaction;
use App\Actions\RetinaAction;
use App\Actions\Traits\WithActionUpdate;
use App\Models\Catalogue\Product;
use App\Models\Ordering\Order;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;

class StoreRetinaTransaction extends RetinaAction
{
    use AsAction;
    use WithAttributes;
    use WithActionUpdate;

    public function handle(Order $order, array $modelData): Order
    {
        foreach (Arr::get($modelData, 'products') as $productData) {
            $product = Product::find(Arr::get($productData, 'id'));

            if ($product) {
                StoreTransaction::make()->action($order, $product->historicAsset, [
                    'quantity_ordered' => Arr::get($productData, 'quantity')
                ]);
            }
        }

        return $order;
    }

    public function rules(): array
    {
        return [
            'products' => ['required', 'array']
        ];
    }

    public function authorize(ActionRequest $request): bool
    {
        return true;
    }

    public function asController(Order $order, ActionRequest $request): Order
    {
        $this->initialisation($request);

        return $this->handle($order, $this->validatedData);
    }
}
