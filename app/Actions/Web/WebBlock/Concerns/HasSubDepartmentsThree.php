<?php

/*
 * author Louis Perez
 * created on 06-06-2026-14h-44m
 * GitHub: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Web\WebBlock\Concerns;

use App\Actions\Catalogue\ProductCategory\Json\GetFamiliesUnderDepartmentPage;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Http\Resources\Web\WebBlockFamilyResourceForDepartmentWebpage;
use App\Models\Web\Webpage;
use Illuminate\Support\Facades\DB;

trait HasSubDepartmentsThree
{
    protected function getSubDepartmentsThree(Webpage $webpage, array $webBlock): array
    {
        $subDepartmentList = DB::table('product_categories')
            ->where('product_categories.department_id', $webpage->model_id)
            ->where('product_categories.shop_id', $webpage->shop_id)
            ->leftJoin('webpages', function ($join) {
                $join->on('product_categories.id', '=', 'webpages.model_id')
                    ->where('webpages.model_type', '=', 'ProductCategory');
            })
            ->select(
                [
                    'product_categories.code',
                    'product_categories.name',
                ]
            )
            ->orderBy('product_categories.code')
            ->where('product_categories.type', ProductCategoryTypeEnum::SUB_DEPARTMENT)
            ->where('product_categories.show_in_website', true)
            ->whereNotNull('webpages.id')
            ->where('webpages.state', WebpageStateEnum::LIVE->value)
            ->whereNull('product_categories.deleted_at')
            ->get()
            ->toArray();

        $familiesList = GetFamiliesUnderDepartmentPage::run($webpage->model);

        data_set($webBlock, 'web_block.layout.data.fieldValue', $webpage->website->published_layout['sub_department']['data']['fieldValue'] ?? []);
        data_set($webBlock, 'web_block.layout.data.fieldValue.sub_department_list', $subDepartmentList ?? []);
        data_set($webBlock, 'web_block.layout.data.fieldValue.families', WebBlockFamilyResourceForDepartmentWebpage::collection($familiesList) ?? []);

        return $webBlock;
    }
}
