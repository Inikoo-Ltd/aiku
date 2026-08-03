<?php

namespace App\Actions\Catalogue\ProductCategory\Json;

use App\Actions\OrgAction;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Http\Resources\Catalogue\ProductCategoryForRelatedWebBlockResource;
use App\Models\Catalogue\ProductCategory;
use App\Models\Catalogue\Shop;
use App\Services\QueryBuilder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\ActionRequest;

class GetProductCategoryForRelatedWebBlock extends OrgAction
{
    public function handle(Shop $parent, ?ProductCategoryTypeEnum $scope = null, ?string $prefix = null)
    {
        $queryBuilder = QueryBuilder::for(ProductCategory::class);

        $queryBuilder->leftJoin('shops', 'product_categories.shop_id', 'shops.id');
        $queryBuilder->leftJoin('webpages', function ($join) use ($scope) {
            $join->on('product_categories.id', 'webpages.model_id')
                ->where('webpages.model_type', 'ProductCategory')
                ->where('webpages.layout_style', 'main_page')
                ->where('webpages.state', WebpageStateEnum::LIVE->value);
        });

        if (class_basename($parent) == 'Shop') {
            $queryBuilder->where('product_categories.shop_id', $parent->id);
        }

        $queryBuilder->when(
            $scope,
            fn ($q) => $q->where('product_categories.type', $scope)
        );
        $queryBuilder->where('show_in_website', true);
        $queryBuilder->whereNotNull('webpages.id');
        $queryBuilder->whereIn('product_categories.state', [
            ProductCategoryStateEnum::ACTIVE->value,
            ProductCategoryStateEnum::DISCONTINUING->value,
        ]);

        $selects = [
            'product_categories.id',
            'product_categories.type',
            'product_categories.slug',
            'product_categories.code',
            'product_categories.name',
            'product_categories.description',
            'product_categories.web_images',
            'webpages.url as shorthand_url',
            'webpages.canonical_url',
        ];

        return $queryBuilder
            ->select($selects)
            ->defaultSort('product_categories.code')
            ->allowedSorts(['code'])
            ->withPaginator($prefix, tableName: request()->route()->getName())
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $productCategories): AnonymousResourceCollection
    {
        return ProductCategoryForRelatedWebBlockResource::collection($productCategories);
    }

    public function asController(Shop $shop, ActionRequest $request)
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop);
    }

    public function onlyDepartment(Shop $shop, ActionRequest $request)
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, ProductCategoryTypeEnum::DEPARTMENT);
    }

    public function onlySubDepartment(Shop $shop, ActionRequest $request)
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, ProductCategoryTypeEnum::SUB_DEPARTMENT);
    }

    public function onlyFamily(Shop $shop, ActionRequest $request)
    {
        $this->initialisationFromShop($shop, $request);

        return $this->handle($shop, ProductCategoryTypeEnum::FAMILY);
    }
}
