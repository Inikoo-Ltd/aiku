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
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class GetIrisLastOrderedProducts extends IrisAction
{
    public function handle(ProductCategory $productCategory): \Illuminate\Support\Collection
    {
        $latestUniqueTransactions = DB::table('transactions')
            ->select([
                'transactions.*',
                DB::raw("
                    ROW_NUMBER() OVER (
                        PARTITION BY transactions.model_id
                        ORDER BY transactions.submitted_at DESC
                    ) as rn
                ")
            ])
            ->whereNotNull('transactions.submitted_at')
            ->where('transactions.model_type', 'Product')
            ->whereIn('transactions.state', [
                TransactionStateEnum::SUBMITTED,
                TransactionStateEnum::IN_WAREHOUSE,
                TransactionStateEnum::HANDLING,
                TransactionStateEnum::PACKED,
                TransactionStateEnum::FINALISED,
            ]);

        $latestUniqueTransactionsQuery = DB::query()
            ->fromSub($latestUniqueTransactions, 'ranked_transactions')
            ->where('rn', 1);

        return DB::table('products')
            ->joinSub($latestUniqueTransactionsQuery, 'latest_tx', function ($join) {
                $join->on('latest_tx.model_id', '=', 'products.id');
            })
            ->join('webpages', function ($join) {
                $join->on('webpages.model_id', '=', 'products.id')
                    ->where('webpages.model_type', 'Product')
                    ->where('webpages.state', WebpageStateEnum::LIVE->value);
            })
            ->join('customers', 'latest_tx.customer_id', '=', 'customers.id')
            ->join('addresses', 'addresses.id', '=', 'customers.address_id')
            ->where('products.family_id', $productCategory->id)
            ->where('products.is_for_sale', true)
            ->orderBy('latest_tx.submitted_at', 'desc')
            ->limit(15)
            ->select([
                'products.*',
                'webpages.canonical_url',
                'latest_tx.submitted_at',
                'latest_tx.id as transaction_id',
                'customers.contact_name as customer_contact_name',
                'customers.name as customer_name',
                'addresses.country_code as customer_country_code',
            ])
            ->get();

    }

    public function jsonResponse($products): AnonymousResourceCollection
    {
        return LastOrderedProductsResource::collection($products);
    }

    public function asController(ProductCategory $productCategory, ActionRequest $request): \Illuminate\Support\Collection
    {
        $this->initialisation($request);

        return $this->handle(productCategory: $productCategory);
    }

}
