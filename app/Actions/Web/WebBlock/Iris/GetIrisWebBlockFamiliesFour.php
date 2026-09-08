<?php

/*
 * author Louis Perez
 * created on 06-06-2026-14h-44m
 * GitHub: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Web\WebBlock\Iris;

use App\Actions\Web\WebBlock\Concerns\HasSubDepartmentsThree;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetIrisWebBlockFamiliesFour
{
    use AsObject;
    use HasSubDepartmentsThree;

    public function handle(Webpage $webpage, array $webBlock): ?array
    {
        /** @var ProductCategory $productCategory */
        $productCategory = $webpage->model;

        $supportedTypes = [ProductCategoryTypeEnum::DEPARTMENT, ProductCategoryTypeEnum::SUB_DEPARTMENT];

        if (!$productCategory instanceof ProductCategory || !in_array($productCategory->type, $supportedTypes)) {
            return null;
        }

        $webBlock = $this->getSubDepartmentsThree($webpage, $webBlock, 'family');

        data_set($webBlock, 'web_block.layout.data.fieldValue.product_category', [
            'slug' => $productCategory->slug,
            'name' => $productCategory->name,
            'type' => $productCategory->type,
        ]);
        data_set($webBlock, 'web_block.layout.data.fieldValue.product_category_title', $productCategory->name);

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
