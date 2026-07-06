<?php

/*
 * author Louis Perez
 * created on 06-06-2026-14h-44m
 * GitHub: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Web\WebBlock\Workshop;

use App\Actions\Web\WebBlock\Concerns\HasSubDepartmentsThree;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebBlockSubDepartmentsThree
{
    use AsObject;
    use HasSubDepartmentsThree;

    public function handle(Webpage $webpage, array $webBlock): ?array
    {
        /** @var ProductCategory $department */
        $department = $webpage->model;

        if (!$department instanceof ProductCategory || $department->type != ProductCategoryTypeEnum::DEPARTMENT) {
            return null;
        }
         $permissions = [''];

        data_set($webBlock, 'web_block.layout.data.permissions', $permissions);
        data_set($webBlock, 'web_block.layout.data.fieldValue.product_category_title', $department->name);

        return $this->getSubDepartmentsThree($webpage, $webBlock);
    }
}
