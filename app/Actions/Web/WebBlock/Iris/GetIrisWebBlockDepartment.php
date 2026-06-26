<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 16 May 2025 12:28:40 Central Indonesia Time, Beach Office, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\WebBlock\Iris;

use App\Actions\Web\WebBlock\Concerns\HasDepartmentData;
use App\Actions\Web\WebBlock\Concerns\HasIrisWebBlockResponse;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsObject;

class GetIrisWebBlockDepartment
{
    use AsObject;
    use HasDepartmentData;
    use HasIrisWebBlockResponse;

    public function handle(Webpage $webpage, array $webBlock): array
    {
        $this->setDepartmentData($webpage, $webBlock);

        return $this->irisResponse($webBlock);
    }
}
