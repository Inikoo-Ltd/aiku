<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 27 Apr 2023 16:37:19 Malaysia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2023, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterCollection\UI;

use App\Actions\GrpAction;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Http\Resources\Masters\MasterDepartmentsResource;
use App\Models\Masters\MasterCollection;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetMasterSubDepartments extends GrpAction
{
    public function asController(MasterShop $masterShop, MasterCollection $scope, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($masterShop->group, $request);

        return $this->handle(parent: $masterShop, scope: $scope);
    }

    public function handle(MasterShop $parent, MasterCollection $scope, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('master_product_categories.name', $value)
                    ->orWhereStartWith('master_product_categories.slug', $value);
            });
        });

        $queryBuilder = QueryBuilder::for(MasterProductCategory::class);
        $queryBuilder->where('master_product_categories.master_shop_id', $parent->id);
        $queryBuilder->whereNotIn('master_product_categories.id', $scope->departments()->pluck('model_id'));
        return $queryBuilder
            ->defaultSort('master_product_categories.code')
            ->select([
                'master_product_categories.id',
                'master_product_categories.slug',
                'master_product_categories.code',
                'master_product_categories.name',
                'master_product_categories.description',
                'master_product_categories.created_at',
                'master_product_categories.updated_at',
                'master_product_category_stats.number_current_families',
                'master_product_category_stats.number_current_products',
            ])
            ->leftJoin('master_product_category_stats', 'master_product_categories.id', 'master_product_category_stats.master_product_category_id')
            ->where('master_product_categories.type', MasterProductCategoryTypeEnum::SUB_DEPARTMENT)
            ->allowedSorts(['code', 'name','shop_code','number_current_families','number_current_products'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix)
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $departments): AnonymousResourceCollection
    {
        return MasterDepartmentsResource::collection($departments);
    }
}
