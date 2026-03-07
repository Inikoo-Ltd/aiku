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
use App\Models\Catalogue\FamilyHasProductOrdered;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class GetIrisLastOrderedProducts extends IrisAction
{
    public function handle(ProductCategory $productCategory, array $modelData): \Illuminate\Support\Collection
    {
        $ignoredProductId = Arr::pull($modelData, 'ignoredProductId', null);

        $cachedData = FamilyHasProductOrdered::where('family_id', $productCategory->id)->first();

        if ($cachedData && $cachedData->isFresh()) {
            $products = $cachedData->getProductData();

            if ($ignoredProductId) {
                $products = $products->whereNot('id', $ignoredProductId)->take(15);
            }

            return $this->convertCachedDataToCollection($products);
        }

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

        $products = DB::table('products')
            ->joinSub($latestUniqueTransactionsQuery, 'latest_tx', function ($join) {
                $join->on('latest_tx.model_id', '=', 'products.id');
            })
            ->when($ignoredProductId, function ($query) use ($ignoredProductId) {
                $query->whereNot('products.id', $ignoredProductId);
            })
            ->leftJoin('webpages', function ($join) {
                $join->on('webpages.model_id', '=', 'products.id')
                    ->where('webpages.model_type', 'Product')
                    ->where('webpages.state', WebpageStateEnum::LIVE->value);
            })
            ->leftJoin('customers', 'latest_tx.customer_id', '=', 'customers.id')
            ->leftJoin('addresses', 'addresses.id', '=', 'customers.address_id')
            ->where('products.family_id', $productCategory->id)
            ->where('products.is_for_sale', true)
            ->orderBy('latest_tx.submitted_at', 'desc')
            ->limit(15)
            ->select([
                'products.id',
                'products.slug',
                'products.code',
                'products.name',
                'products.unit_price',
                'webpages.canonical_url',
                'webpages.url as webpage_url',
                'latest_tx.submitted_at',
                'latest_tx.id as transaction_id',
                'latest_tx.net_amount',
                'customers.contact_name as customer_contact_name',
                'customers.contact_name_components',
                'customers.name as customer_name',
                'customers.slug as customer_slug',
                'addresses.country_code as customer_country_code',
            ])
            ->get();

        // Cache the results for future requests
        $this->cacheResults($productCategory, $products);

        return $products;
    }

    /**
     * Convert cached JSON data back to the expected collection format
     */
    private function convertCachedDataToCollection(\Illuminate\Support\Collection $products): \Illuminate\Support\Collection
    {
        return $products->map(function ($product) {
            $flatProduct = [
                'id' => $product['id'],
                'slug' => $product['slug'],
                'code' => $product['code'],
                'name' => $product['name'],
                'unit_price' => $product['unit_price'],
                'canonical_url' => $product['webpage']['canonical_url'] ?? null,
                'webpage_url' => $product['webpage']['url'] ?? null,
                'submitted_at' => $product['ordered_date'],
                'transaction_id' => $product['transaction_id'],
                'net_amount' => $product['net_amount'],
                'customer_contact_name' => $product['customer']['contact_name'] ?? null,
                'contact_name_components' => $product['customer']['contact_name_components'] ?? null,
                'customer_name' => $product['customer']['name'] ?? null,
                'customer_slug' => $product['customer']['slug'] ?? null,
                'customer_country_code' => $product['customer']['country_code'] ?? null,
            ];

            return (object) $flatProduct;
        });
    }

    /**
     * Cache query results for future requests
     */
    private function cacheResults(ProductCategory $productCategory, \Illuminate\Support\Collection $products): void
    {
        // Format data for storage
        $formattedProducts = $products->map(function ($product) {
            return [
                'id' => $product->id,
                'slug' => $product->slug,
                'code' => $product->code,
                'name' => $product->name,
                'unit_price' => $product->unit_price,
                'ordered_date' => $product->submitted_at,
                'transaction_id' => $product->transaction_id,
                'net_amount' => $product->net_amount,
                'customer' => [
                    'name' => $product->customer_name,
                    'slug' => $product->customer_slug,
                    'contact_name' => $product->customer_contact_name,
                    'contact_name_components' => $product->contact_name_components,
                    'country_code' => $product->customer_country_code,
                ],
                'webpage' => [
                    'canonical_url' => $product->canonical_url,
                    'url' => $product->webpage_url,
                ],
            ];
        })->toArray();

        // Update or create cached record
        FamilyHasProductOrdered::updateOrCreate(
            ['family_id' => $productCategory->id],
            ['product' => $formattedProducts]
        );
    }

    public function rules(): array
    {
        return [
            'ignoredProductId'    => ['sometimes', 'string'],
        ];
    }

    public function jsonResponse($products): AnonymousResourceCollection
    {
        return LastOrderedProductsResource::collection($products);
    }

    public function asController(ProductCategory $productCategory, ActionRequest $request): \Illuminate\Support\Collection
    {
        $this->initialisation($request);

        return $this->handle($productCategory, $this->validatedData);
    }

}
