<?php

/*
 * author Louis Perez
 * created on 11-06-2026-09h-43m
 * GitHub: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Web\WebBlock\Workshop;

use App\Actions\Web\WebBlock\Traits\WithFamiliesQuery;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Http\Resources\Web\WebBlockFamiliesResource;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsObject;

class GetTopFamilies
{
    use AsObject;
    use WithFamiliesQuery;

    public function handle(Webpage $webpage, array $webBlock): ?array
    {

        /** @var ProductCategory $department */
        $productCategory = $webpage->model;

        if (!$productCategory instanceof ProductCategory || $productCategory->type == ProductCategoryTypeEnum::FAMILY) {
            return null;
        }

        $families = $this->getFamilyList($webpage)
            ->orderBy('yearly_sales.total_sales', 'desc')
            ->limit(6)
            ->get();

        data_set($webBlock, 'web_block.layout.data.fieldValue.families', WebBlockFamiliesResource::collection($families)->resolve());
        data_set($webBlock, 'web_block.layout.data.fieldValue.product_category_title', $productCategory->name);

        return $webBlock;
    }
}
