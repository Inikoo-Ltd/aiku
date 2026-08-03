<?php

/*
 * author Louis Perez
 * created on 21-06-2026-01h-46m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Web\WebBlock\Concerns;

use App\Http\Resources\Web\WebBlockProductCategoryDescriptionResource;
use App\Models\Web\Webpage;

trait HasDepartmentData
{
    protected function setDepartmentData(Webpage $webpage, array &$webBlock): void
    {
        data_set($webBlock, 'web_block.layout.data.fieldValue.department', WebBlockProductCategoryDescriptionResource::make($webpage->model)->toArray(request()));
    }
}
