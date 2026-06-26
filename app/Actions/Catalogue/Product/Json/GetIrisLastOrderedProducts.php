<?php

/*
 * author Arya Permana - Kirin
 * created on 06-12-2024-10h-47m
 * GitHub: https://github.com/KirinZero0
 * copyright 2024
*/

namespace App\Actions\Catalogue\Product\Json;

use App\Actions\IrisAction;
use App\Enums\Ordering\Transaction\TransactionStateEnum;
use App\Http\Resources\Catalogue\LastOrderedProductsResource;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\ActionRequest;

class GetIrisLastOrderedProducts extends IrisAction
{
    public function handle(ProductCategory $productCategory, array $modelData): \Illuminate\Support\Collection
    {
        $ignoredProductId = Arr::get($modelData, 'ignoredProductId');
        $cacheKey = sprintf(
            'iris:last_ordered_products:%s:%s',
            $productCategory->id,
            $ignoredProductId ?? 'none'
        );

        $cachedProducts = Cache::get($cacheKey);
        if ($cachedProducts !== null) {
            return $cachedProducts;
        }

        $query = DB::table('transactions')
            ->select([
                'products.code',
                'products.name',
                'products.web_images',
                'webpages.canonical_url',
                'transactions.date as submitted_at',
            ])
            ->leftJoin('products', 'transactions.model_id', '=', 'products.id')
            ->leftJoin('webpages', 'products.webpage_id', '=', 'webpages.id')
            ->where('webpages.state', WebpageStateEnum::LIVE->value)
            ->where('products.is_for_sale', true)
            ->where('transactions.family_id', $productCategory->id)
            ->where('transactions.state', '!=', TransactionStateEnum::CREATING);


        if ($ignoredProductId) {
            $query->where('products.id', '!=', $ignoredProductId);
        }

        $itemsInQuery = (clone $query)->count();
        $products = $query->orderBy('date', 'desc')->limit(10)->get();

        Cache::put($cacheKey, $products, $this->cacheTtl($itemsInQuery));

        return $products;


    }

    public function cacheTtl(int $itemsInQuery): \DateTimeInterface
    {
        if ($itemsInQuery < 4) {
            return now()->addMinutes(30);
        }

        if ($itemsInQuery < 10) {
            return now()->addHours(6);
        }

        return now()->addDay();
    }

    public function rules(): array
    {
        return [
            'ignoredProductId' => ['sometimes', 'string'],
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
