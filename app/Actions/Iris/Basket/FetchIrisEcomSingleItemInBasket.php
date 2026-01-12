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
use App\Http\Resources\Web\WebBlockProductResource;
use App\Models\Catalogue\Product;
use App\Models\Ordering\Transaction;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class FetchIrisEcomSingleItemInBasket extends IrisAction
{
    use WithIrisProductsInWebpage;

    private $returnType = 'default';

    public function handle(Transaction $transaction)
    {
        $product = $transaction->model;
        if (!($product instanceof Product)) {
            abort(404, 'Unable to find the selected product transaction');
        }

        $additionalSelect = $this->returnType == 'product_page' ? [
            'products.currency_id',
            'products.country_of_origin',
            'products.marketing_ingredients',
            'products.gross_weight',
            'products.barcode',
            'products.marketing_dimensions',
            'products.cpnp_number',
            'products.marketing_weight',
            'products.slug',
            'products.description',
            'products.description_title',
            'products.description_extra',
        ] : [];

        $queryBuilder = $this->getBaseQuery('all');
        $queryBuilder
            ->where('products.id', $product->id)
            ->where('transactions.id', $transaction->id);
        $queryBuilder->select(
            $this->getSelect([
                ...$additionalSelect,
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

        if ($this->returnType == 'product_page') {
            $queryBuilder->with([
                'tags',
                'images',
                'contents',
                'backInStockReminders',
            ]);
        }

        return $queryBuilder->first();
    }

    public function asController(Transaction $transaction, ActionRequest $request)
    {
        $this->returnType = 'default';
        $this->initialisation($request);

        return $this->handle($transaction);
    }

    public function inProductPage(Transaction $transaction, ActionRequest $request)
    {
        $this->returnType = 'product_page';
        $this->initialisation($request);

        return $this->handle($transaction);
    }

    public function jsonResponse(Product $product)
    {
        if ($this->returnType == 'product_page') {
            return WebBlockProductResource::make($product)->toArray(request());
        }
        return IrisAuthenticatedProductsInWebpageResource::make($product)->toArray(request());
    }
}
