<?php

/*
 * author Louis Perez
 * created on 16-03-2026-14h-26m
 * GitHub: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Web\WebBlock\Workshop;

use App\Actions\Web\WebBlock\Concerns\HasFaqDepartmentData;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsObject;

class GetFaqDepartment
{
    use AsObject;
    use HasFaqDepartmentData;

    public function handle(Webpage $webpage, array $webBlock): ?array
    {
        /** @var ProductCategory $department */
        $department = $webpage->model;

        if (!$department instanceof ProductCategory || $department->type != ProductCategoryTypeEnum::DEPARTMENT) {
            return null;
        }
        $this->setFaqDepartmentData($webpage, $webBlock);

        return $webBlock;
    }
}
