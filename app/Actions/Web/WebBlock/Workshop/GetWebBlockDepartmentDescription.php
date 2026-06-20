<?php

/*
 * author Louis Perez
 * created on 09-06-2026-13h-44m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Web\WebBlock\Workshop;

use App\Actions\Web\WebBlock\Concerns\HasSubDepartmentList;
use App\Actions\Web\WebBlock\Concerns\HasWebBlockLayoutData;
use App\Http\Resources\Web\WebBlockDepartmentResource;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebBlockDepartmentDescription
{
    use AsObject;
    use HasSubDepartmentList;
    use HasWebBlockLayoutData;

    public function handle(Webpage $webpage, array $webBlock): array
    {
        $subDepartmentList = $this->getSubDepartmentList($webpage);

        $this->setWebBlockLayoutData($webpage, $webBlock, 'department_description');

        data_set($webBlock, 'web_block.layout.data.fieldValue.department', WebBlockDepartmentResource::make($webpage->model)->resolve());
        data_set($webBlock, 'web_block.layout.data.fieldValue.sub_departments', $subDepartmentList);

        return $webBlock;
    }

}
