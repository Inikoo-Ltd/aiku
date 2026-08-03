<?php

/*
 * author Louis Perez
 * created on 09-06-2026-13h-44m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Web\WebBlock\Workshop;

use App\Actions\Web\WebBlock\Concerns\HasDepartmentDescriptionData;
use App\Actions\Web\WebBlock\Concerns\HasSubDepartmentList;
use App\Actions\Web\WebBlock\Concerns\HasWebBlockLayoutData;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebBlockDepartmentDescription
{
    use AsObject;
    use HasDepartmentDescriptionData;
    use HasSubDepartmentList;
    use HasWebBlockLayoutData;

    public function handle(Webpage $webpage, array $webBlock): ?array
    {
        if (!$this->setDepartmentDescriptionData($webpage, $webBlock)) {
            return null;
        }

        $permissions = [''];

        data_set($webBlock, 'web_block.layout.data.permissions', $permissions);
        return $webBlock;
    }
}
