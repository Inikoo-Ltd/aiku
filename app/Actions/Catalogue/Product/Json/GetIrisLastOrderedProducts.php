<?php

/*
 * author Arya Permana - Kirin
 * created on 06-12-2024-10h-47m
 * github: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Catalogue\Product\Json;

use App\Actions\IrisAction;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Http\Resources\Catalogue\LastOrderedProductsResource;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class GetIrisLastOrderedProducts extends IrisAction
{
    public function handle(ProductCategory $productCategory): \Illuminate\Support\Collection
    {
        return DB::table('products')
            ->select('products.*', 'transactions.submitted_at', 'transactions.id as transaction_id')
            ->where('products.family_id', $productCategory->id)
            ->join('transactions', function ($join) {
                $join->on('transactions.model_id', '=', 'products.id')
                    ->where('transactions.model_type', '=', 'Product')
                    ->where('transactions.state', '=', TransactionStateEnum::SUBMITTED)
                    ->whereRaw(
                        'transactions.submitted_at = (
                        SELECT MAX(t2.submitted_at)
                        FROM transactions t2
                        WHERE t2.model_id = products.id
                            AND t2.model_type = ?
                            AND t2.state = ?
                    )',
                        ['Product', TransactionStateEnum::SUBMITTED]
                    );
            })
            ->orderBy('transactions.submitted_at', 'desc')
            ->limit(10)
            ->get();
    }

    public function jsonResponse(LengthAwarePaginator $products): AnonymousResourceCollection
    {
        return LastOrderedProductsResource::collection($products);
    }

    public function asController(ProductCategory $productCategory, ActionRequest $request): \Illuminate\Support\Collection
    {
        $this->initialisation($request);

        return $this->handle(productCategory: $productCategory);
    }

}
