<?php

/*
 * author Louis Perez
 * created on 06-06-2026-14h-44m
 * GitHub: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Web\WebBlock\Concerns;

use App\Actions\Catalogue\ProductCategory\Json\GetFamiliesUnderDepartmentPage;
use App\Enums\Catalogue\Collection\CollectionStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Http\Resources\Web\FamiliesInDepartmentWebpageResource;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\Webpage;
use Illuminate\Support\Facades\DB;

trait HasSubDepartmentsThree
{
    protected function getSubDepartmentsThree(Webpage $webpage, array $webBlock): array
    {
        $parent = $webpage->model;

        $subDepartmentList = DB::table('product_categories')
            ->where('product_categories.department_id', $parent->id)
            ->where('product_categories.shop_id', $webpage->shop_id)
            ->leftJoin('webpages', function ($join) {
                $join->on('product_categories.id', '=', 'webpages.model_id')
                    ->where('webpages.model_type', '=', 'ProductCategory');
            })
            ->select(
                [
                    'product_categories.code',
                    'product_categories.name',
                    'webpages.canonical_url as url',
                ]
            )
            ->orderBy('product_categories.code')
            ->where('product_categories.type', ProductCategoryTypeEnum::SUB_DEPARTMENT)
            ->whereIn('product_categories.state', [
                ProductCategoryStateEnum::ACTIVE,
                ProductCategoryStateEnum::DISCONTINUING
            ])
            ->where('product_categories.show_in_website', true)
            ->whereNotNull('webpages.id')
            ->where('webpages.state', WebpageStateEnum::LIVE->value)
            ->whereNull('product_categories.deleted_at')
            ->get()
            ->toArray();

        $collectionList = $parent->collections()->where('collections.state', CollectionStateEnum::ACTIVE)
            ->leftJoin('webpages', function ($join) {
                $join->on('collections.id', '=', 'webpages.model_id')
                    ->where('webpages.model_type', '=', 'Collection');
            })
            ->select([
                'collections.code',
                'collections.name',
                'webpages.canonical_url as url',
            ])
            ->whereExists(function ($query) {
                $query->selectRaw(1)
                    ->from('collection_has_models as chm')
                    ->whereColumn('chm.collection_id', 'collections.id')
                    ->where('chm.model_type', class_basename(ProductCategory::class));
            })
            ->get()
            ->toArray();

        $familiesList = GetFamiliesUnderDepartmentPage::run($parent);

        data_set($webBlock, 'web_block.layout.data.fieldValue', $webpage->website->published_layout['sub_department']['data']['fieldValue'] ?? []);
        data_set($webBlock, 'web_block.layout.data.fieldValue.sub_department_list', $subDepartmentList ?? []);
        data_set($webBlock, 'web_block.layout.data.fieldValue.collections_list', $collectionList ?? []);
        data_set($webBlock, 'web_block.layout.data.fieldValue.filter_options', array_merge($subDepartmentList ?? [], $collectionList ?? []));
        data_set($webBlock, 'web_block.layout.data.fieldValue.families', FamiliesInDepartmentWebpageResource::collection($familiesList) ?? []);

        return $webBlock;
    }
}
