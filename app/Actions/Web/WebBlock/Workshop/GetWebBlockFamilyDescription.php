<?php

/*
 * author Louis Perez
 * created on 16-03-2026-14h-26m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Web\WebBlock\Workshop;

use App\Actions\Web\WebBlock\Concerns\HasFamilyDescriptionData;
use App\Actions\Web\WebBlock\Concerns\HasWebBlockLayoutData;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebBlockFamilyDescription
{
    use AsObject;
    use HasFamilyDescriptionData;
    use HasWebBlockLayoutData;

    public function handle(Webpage $webpage, array $webBlock): array
    {
        $this->setFamilyDescriptionData($webpage, $webBlock);
        $permissions =  [];

        data_set($webBlock, 'web_block.layout.data.permissions', $permissions);
        return $webBlock;
    }
}
