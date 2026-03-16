<?php

/*
 * author Louis Perez
 * created on 03-02-2026-09h-57m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Catalogue\Product\Json;

use App\Actions\OrgAction;
use App\Http\Resources\Catalogue\LastOrderedProductsResource;
use App\Models\Catalogue\ProductCategory;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;

class GetLastOrderedProducts extends OrgAction
{
    public function handle(ProductCategory $productCategory, array $modelData): \Illuminate\Support\Collection
    {
        return GetIrisLastOrderedProducts::run($productCategory, $modelData);
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
        $this->initialisation($productCategory->organisation, $request);

        return $this->handle($productCategory, $this->validatedData);
    }

}
