<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 16 May 2025 12:28:40 Central Indonesia Time, Beach Office, Bali, Indonesia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Web\WebBlock\Workshop;

use App\Actions\Web\WebBlock\Concerns\HasDepartmentData;
use App\Models\Web\Webpage;
use Lorisleiva\Actions\Concerns\AsObject;

class GetWebBlockDepartment
{
    use AsObject;
    use HasDepartmentData;

    public function handle(Webpage $webpage, array $webBlock): array
    {
        $permissions = ['edit', 'hidden'];

        data_set($webBlock, 'web_block.layout.data.permissions', $permissions);
        $this->setDepartmentData($webpage, $webBlock);

        return $webBlock;
    }
}
