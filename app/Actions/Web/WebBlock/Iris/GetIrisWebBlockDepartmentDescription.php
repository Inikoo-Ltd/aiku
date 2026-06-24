<?php

/*
 * author Louis Perez
 * created on 09-06-2026-14h-07m
 * GitHub: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Web\WebBlock\Iris;

use App\Actions\Web\WebBlock\Concerns\HasDepartmentDescriptionData;
use App\Actions\Web\WebBlock\Concerns\HasIrisWebBlockResponse;
use App\Actions\Web\WebBlock\Concerns\HasSubDepartmentList;
use App\Actions\Web\WebBlock\Concerns\HasWebBlockLayoutData;
use App\Actions\Web\WebBlock\GetWebBlockCollections;
use App\Http\Resources\Web\WebBlockCollectionResource;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsObject;

class GetIrisWebBlockDepartmentDescription
{
    use AsObject;
    use HasDepartmentDescriptionData;
    use HasIrisWebBlockResponse;
    use HasSubDepartmentList;
    use HasWebBlockLayoutData;

    public function handle(Webpage $webpage, array $webBlock): ?array
    {
        if (!$this->setDepartmentDescriptionData($webpage, $webBlock)) {
            return null;
        }

        data_set($webBlock, 'web_block.layout.data.fieldValue.collections', WebBlockCollectionResource::collection(GetWebBlockCollections::make()->getCollections($webpage))->toArray(request()));

        return $this->irisResponse($webBlock);
    }
}
