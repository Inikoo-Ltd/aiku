<?php

/*
 * Author: Artha <artha@aw-advantage.com>
 * Created: Mon, 11 Aug 2025 16:08:25 Central Indonesia Time, Sanur, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory\Json;

use App\Actions\GrpAction;
use App\Enums\Catalogue\MasterProductCategory\MasterProductCategoryTypeEnum;
use App\Http\Resources\Catalogue\MasterProductCategoryResource;
use App\Models\Masters\MasterProductCategory;
use App\Models\Masters\MasterShop;
use App\Services\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Lorisleiva\Actions\ActionRequest;
use Spatie\QueryBuilder\AllowedFilter;

class GetMasterDepartmentAndMasterSubDepartments extends GrpAction
{
    public function asController(MasterShop $masterShop, ActionRequest $request): LengthAwarePaginator
    {
        $this->initialisation($masterShop->group, $request);

        return $this->handle($masterShop);
    }

    public function handle(MasterShop $masterShop, $prefix = null): LengthAwarePaginator
    {
        $globalSearch = AllowedFilter::callback('global', function ($query, $value) {
            $query->where(function ($query) use ($value) {
                $query->whereAnyWordStartWith('master_product_categories.name', $value)
                    ->orWhereStartWith('master_product_categories.slug', $value);
            });
        });

        $queryBuilder = QueryBuilder::for(MasterProductCategory::class);
        $queryBuilder->where('master_product_categories.master_shop_id', $masterShop->id);
        $queryBuilder->whereIn('type', [MasterProductCategoryTypeEnum::DEPARTMENT, MasterProductCategoryTypeEnum::SUB_DEPARTMENT]);
        $queryBuilder->whereNull('deleted_at');
        
        return $queryBuilder
            ->defaultSort('master_product_categories.code')
            ->select([
                'master_product_categories.id',
                'master_product_categories.slug',
                'master_product_categories.code',
                'master_product_categories.name',
                'master_product_categories.type',
                'master_product_categories.description',
                'master_product_categories.created_at',
                'master_product_categories.updated_at',
                'master_product_category_stats.number_current_products'
            ])
            ->leftJoin('master_product_category_stats', 'master_product_categories.id', 'master_product_category_stats.master_product_category_id')
            ->allowedSorts(['code', 'name', 'shop_code', 'department_code'])
            ->allowedFilters([$globalSearch])
            ->withPaginator($prefix)
            ->withQueryString();
    }

    public function jsonResponse(LengthAwarePaginator $families): AnonymousResourceCollection
    {
        return MasterProductCategoryResource::collection($families);
    }
}
