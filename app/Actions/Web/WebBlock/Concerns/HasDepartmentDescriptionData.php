<?php

/*
 * author Louis Perez
 * created on 21-06-2026-01h-30m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Web\WebBlock\Concerns;

use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Http\Resources\Web\WebBlockDepartmentResource;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\Webpage;

trait HasDepartmentDescriptionData
{
    protected function setDepartmentDescriptionData(Webpage $webpage, array &$webBlock): bool
    {
        /** @var ProductCategory $department */
        $department = $webpage->model;

        if (!$department instanceof ProductCategory || $department->type != ProductCategoryTypeEnum::DEPARTMENT) {
            return false;
        }

        $subDepartmentList = $this->getSubDepartmentList($webpage);

        $this->setWebBlockLayoutData($webpage, $webBlock, 'department_description');

        data_set($webBlock, 'web_block.layout.data.fieldValue.department', WebBlockDepartmentResource::make($webpage->model)->resolve());
        data_set($webBlock, 'web_block.layout.data.fieldValue.sub_departments', $subDepartmentList);

        return true;
    }
}
