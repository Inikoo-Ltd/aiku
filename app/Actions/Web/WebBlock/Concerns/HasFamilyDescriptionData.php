<?php

/*
 * author Louis Perez
 * created on 21-06-2026-01h-38m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Web\WebBlock\Concerns;

use App\Http\Resources\Web\WebBlockFamilyResource;
use App\Models\Web\Webpage;

trait HasFamilyDescriptionData
{
    protected function setFamilyDescriptionData(Webpage $webpage, array &$webBlock): void
    {
        $this->setWebBlockLayoutData($webpage, $webBlock, 'family_description');

        data_set($webBlock, 'web_block.layout.data.fieldValue.family', WebBlockFamilyResource::make($webpage->model)->toArray(request()));
    }
}
