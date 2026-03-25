<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 06 Jul 2025 11:03:42 British Summer Time, Sheffield, UK
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Catalogue\Product\UI;

use App\Actions\OrgAction;
use App\Http\Resources\CRM\ProductsForPortfolioSelectResource;
use App\Http\Resources\CRM\SelectedProductsForBundleResource;
use App\Http\Resources\Helpers\ImagesResource;
use App\InertiaTable\InertiaTable;
use App\Models\Catalogue\Product;
use App\Models\Helpers\Media;
use App\Services\QueryBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\ActionRequest;

class IndexBulkProductImages extends OrgAction
{
    public function handle(array $modelData, $prefix = null): Collection
    {
        if ($prefix) {
            InertiaTable::updateQueryBuilderParameters($prefix);
        }

        $queryBuilder = QueryBuilder::for(Product::class);
        $queryBuilder->whereIn('id', Arr::get($modelData, 'product_ids'))
                    ->with('images');

        $queryBuilder
            ->defaultSort('products.id');

        return $queryBuilder->get();
    }

    public function rules(): array
    {
        return [
            'product_ids' => ['required', 'array'],
            'product_ids.*' => ['required', 'integer', 'exists:products,id']
        ];
    }

    public function jsonResponse(Collection $images): AnonymousResourceCollection
    {
        return SelectedProductsForBundleResource::collection($images);
    }
}
