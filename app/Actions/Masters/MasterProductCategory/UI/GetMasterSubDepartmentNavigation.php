<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 21 Dec 2025 22:15:25 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Masters\MasterProductCategory\UI;

use App\Models\Masters\MasterProductCategory;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsObject;

class GetMasterSubDepartmentNavigation
{
    use WithMasterSubDepartmentNavigation;
    use AsObject;

    public function handle(MasterProductCategory $masterProductCategory, ActionRequest $request): array
    {
        return [
            'previous' => $this->getPreviousModel($masterProductCategory, $request),
            'next'     => $this->getNextModel($masterProductCategory, $request),
        ];
    }

}
