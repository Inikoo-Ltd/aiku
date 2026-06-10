<?php

/*
 * author Louis Perez
 * created on 09-06-2026-13h-44m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Web\WebBlock\Workshop;

use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Http\Resources\Web\WebBlockDepartmentResource;
use App\Http\Resources\Web\WebBlockSubDepartmentsResource;
use App\Models\Web\Webpage;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebBlockDepartmentDescription
{
    use AsObject;

    public function handle(Webpage $webpage, array $webBlock): array
    {
        $permissions =  ['edit','hidden'];
        $subDepartmentList = DB::table('product_categories')
            ->where('product_categories.department_id', $webpage->model_id)
            ->where('product_categories.shop_id', $webpage->shop_id)
            ->leftJoin('webpages', function ($join) {
                $join->on('product_categories.id', '=', 'webpages.model_id')
                    ->where('webpages.model_type', '=', 'ProductCategory');
            })
            ->select(
                [
                    'product_categories.id',
                    'product_categories.slug',
                    'product_categories.code',
                    'product_categories.name',
                    'webpages.url',
                    'webpages.canonical_url',
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

        $webBlockType = data_get($webBlock, 'type', '');
        $webPublishedLayout = $webpage->website->published_layout;

        data_set($webBlock, 'web_block.layout.data.permissions', $permissions);
        data_set($webBlock, 'web_block.layout.data.fieldValue', data_get($webPublishedLayout, "department_description.$webBlockType.fieldValue", []));
        data_set($webBlock, 'web_block.layout.data.fieldValue.id', data_get($webBlock, 'type'));
        data_set($webBlock, 'web_block.layout.data.fieldValue.department', WebBlockDepartmentResource::make($webpage->model)->resolve());
        data_set($webBlock, 'web_block.layout.data.fieldValue.sub_departments', $subDepartmentList);

        return $webBlock;
    }

}
