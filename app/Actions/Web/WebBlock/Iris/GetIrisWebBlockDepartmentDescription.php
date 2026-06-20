<?php

/*
 * author Louis Perez
 * created on 09-06-2026-14h-07m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Web\WebBlock\Iris;

use App\Actions\Web\WebBlock\Concerns\HasSubDepartmentList;
use App\Actions\Web\WebBlock\Concerns\HasWebBlockLayoutData;
use App\Actions\Web\WebBlock\GetWebBlockCollections;
use App\Enums\Catalogue\ProductCategory\ProductCategoryTypeEnum;
use App\Http\Resources\Web\WebBlockCollectionResource;
use App\Http\Resources\Web\WebBlockDepartmentResource;
use App\Models\Catalogue\ProductCategory;
use App\Models\Web\Webpage;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsObject;

class GetIrisWebBlockDepartmentDescription
{
    use AsObject;
    use HasSubDepartmentList;
    use HasWebBlockLayoutData;

    public function handle(Webpage $webpage, array $webBlock): ?array
    {
        /** @var ProductCategory $department */
        $department=$webpage->model;

        if(!$department instanceof  ProductCategory  || $department->type!=ProductCategoryTypeEnum::DEPARTMENT ){
            return null;
        }

        $subDepartmentList = $this->getSubDepartmentList($webpage);

        $this->setWebBlockLayoutData($webpage, $webBlock, 'department_description');

        data_set($webBlock, 'web_block.layout.data.fieldValue.department', WebBlockDepartmentResource::make($webpage->model)->resolve());
        data_set($webBlock, 'web_block.layout.data.fieldValue.sub_departments', $subDepartmentList);
        data_set($webBlock, 'web_block.layout.data.fieldValue.collections', WebBlockCollectionResource::collection(GetWebBlockCollections::make()->getCollections($webpage))->toArray(request()));

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
