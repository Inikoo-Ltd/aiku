<?php

/*
 * author Louis Perez
 * created on 16-03-2026-14h-26m
 * github: https://github.com/louis-perez
 * copyright 2026
*/

namespace App\Actions\Web\WebBlock\Iris;

use App\Actions\Web\WebBlock\Concerns\HasFamilyDescriptionData;
use App\Actions\Web\WebBlock\Concerns\HasIrisWebBlockResponse;
use App\Actions\Web\WebBlock\Concerns\HasWebBlockLayoutData;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsObject;

class GetIrisWebBlockFamilyDescription
{
    use AsObject;
    use HasFamilyDescriptionData;
    use HasIrisWebBlockResponse;
    use HasWebBlockLayoutData;

    public function handle(Webpage $webpage, array $webBlock): array
    {
        $this->setFamilyDescriptionData($webpage, $webBlock);

        return $this->irisResponse($webBlock);
    }
}
