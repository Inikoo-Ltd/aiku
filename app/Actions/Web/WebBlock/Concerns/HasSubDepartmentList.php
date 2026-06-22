<?php

/*
 * author Louis Perez
 * created on 09-06-2026-13h-44m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Web\WebBlock\Concerns;

use App\Enums\Catalogue\ProductCategory\ProductCategoryStateEnum;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Enums\Web\Webpage\WebpageStateEnum;
use App\Models\Web\Webpage;
use Illuminate\Support\Facades\DB;

trait HasSubDepartmentList
{
    protected function getSubDepartmentList(Webpage $webpage): array
    {
        return DB::table('product_categories')
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
    }
}
