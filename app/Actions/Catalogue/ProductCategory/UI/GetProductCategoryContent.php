<?php

namespace App\Actions\Catalogue\ProductCategory\UI;

/*
 * author Louis Perez
 * created on 28-11-2025-09h-58m
 * github: https://github.com/louis-perez
 * copyright 2025
*/

use App\Http\Resources\Catalogue\ProductResource;
use App\Models\Catalogue\ProductCategory;
use Lorisleiva\Actions\Concerns\AsObject;

class GetProductCategoryContent
{
    use AsObject;

    public function handle(ProductCategory $productCategory): array
    {

        return [
            'productCategory'               => [
                'is_name_reviewed' => $productCategory->is_name_reviewed,
                'is_description_title_reviewed' => $productCategory->is_description_title_reviewed,
                'is_description_reviewed' => $productCategory->is_description_reviewed,
                'is_description_extra_reviewed' => $productCategory->is_description_extra_reviewed,
            ],
        ];
    }

}
