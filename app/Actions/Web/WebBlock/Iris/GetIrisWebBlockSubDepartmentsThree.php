<?php

/*
 * author Louis Perez
 * created on 06-06-2026-14h-44m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Web\WebBlock\Iris;

use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Http\Resources\Web\WebBlockFamilyResourceForDepartmentWebpage;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\Webpage;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;
use Illuminate\Support\Arr;

class GetIrisWebBlockSubDepartmentsThree
{
    use AsObject;

    public function handle(Webpage $webpage, array $webBlock): array
    {
        /** @var ProductCategory $department */
        $department = $webpage->model;

        $subDepartmentList = DB::table('product_categories')->where('department_id', $webpage->model_id)
            ->leftjoin('webpages', function ($join) {
                $join->on('product_categories.id', '=', 'webpages.model_id')
                    ->where('webpages.model_type', '=', 'ProductCategory');
            })
            ->select(
                [
                    'product_categories.id',
                    'product_categories.slug',
                    'product_categories.code',
                    'product_categories.name',
                    'product_categories.web_images',
                    'product_categories.image_id',
                    'webpages.canonical_url'
                ]
            )
            ->orderBy('product_categories.code')
            ->where('product_categories.type', ProductCategoryTypeEnum::SUB_DEPARTMENT)
            ->where('product_categories.show_in_website', true)
            ->whereNotNull('webpages.id')
            ->where('webpages.state', WebpageStateEnum::LIVE->value)
            ->whereNull('product_categories.deleted_at')
            ->get()
            ->pluck('product_categories.name', 'product_categories.code');

        $familiesList = DB::table('product_categories')->where('department_id', $webpage->model_id)
            ->leftjoin('webpages', function ($join) {
                $join->on('product_categories.id', '=', 'webpages.model_id')
                    ->where('webpages.model_type', '=', 'ProductCategory');
            })
            ->select(
                [
                    'product_categories.id',
                    'product_categories.slug',
                    'product_categories.code',
                    'product_categories.name',
                    'product_categories.web_images',
                    'product_categories.image_id',
                    'webpages.canonical_url'
                ]
            )
            ->orderBy('product_categories.code')
            ->where('product_categories.type', ProductCategoryTypeEnum::FAMILY)
            ->where('product_categories.show_in_website', true)
            ->whereNotNull('webpages.id')
            ->where('webpages.state', WebpageStateEnum::LIVE->value)
            ->whereNull('product_categories.deleted_at')
            ->limit(25)
            ->get();

        data_set($webBlock, 'web_block.layout.data.fieldValue', $webpage->website->published_layout['sub_department']['data']['fieldValue'] ?? []);
        data_set($webBlock, 'web_block.layout.data.fieldValue.sub_department_list', $subDepartmentList ?? []);
        data_set($webBlock, 'web_block.layout.data.fieldValue.families', WebBlockFamilyResourceForDepartmentWebpage::collection($familiesList) ?? []);

        return [
           'type' => $webBlock['type'],
           'structure' => Arr::get(
               $webBlock,
               'web_block.layout.data.fieldValue',
               []
           ),
        ];
    }

}
