<?php

/*
 * author Louis Perez
 * created on 09-01-2026-13h-37m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Iris\Basket;

use App\Actions\Catalogue\Product\Json\WithIrisProductsInWebpage;
use App\Actions\IrisAction;
use App\Http\Resources\Catalogue\IrisAuthenticatedProductsInWebpageResource;
use App\Models\Catalogue\Product;
use App\Models\Ordering\Transaction;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class FetchIrisEcomSingleItemInBasket extends IrisAction
{
    use WithIrisProductsInWebpage;

    public function handle(Transaction $transaction, array $modelData)
    {
        $product = $transaction->model;
        if (!($product instanceof Product)) {
            abort(404);
        }

        $queryBuilder = $this->getBaseQuery('all');
        $queryBuilder
            ->where('products.id', $product->id)
            ->where('transactions.id', $transaction->id);
        $queryBuilder->select(
            $this->getSelect([
                DB::raw('products.variant_id IS NOT NULL as is_variant'),
                DB::raw('exists (
                        select os.is_on_demand
                        from org_stocks os
                        join product_has_org_stocks phos on phos.org_stock_id = os.id
                        where phos.product_id = products.id
                        and os.is_on_demand = true
                    ) as is_on_demand')
            ])
        );

        return $queryBuilder->first();
    }

    public function asController(Transaction $transaction, ActionRequest $request)
    {
        $this->initialisation($request);

        return $this->handle($transaction, $this->validatedData);
    }

    public function jsonResponse(Product $product)
    {
        return IrisAuthenticatedProductsInWebpageResource::make($product);
    }
}
