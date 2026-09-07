<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 29 Aug 2024 01:11:27 Central Indonesia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Inventory\LocationOrgStock;

use App\Enums\SysAdmin\Authorisation\WarehousePermissionsEnum;
use Lorisleiva\Actions\ActionRequest;

trait WithLocationOrgStockActionAuthorisation
{
    public function authorize(ActionRequest $request)
    {
        if ($this->asAction) {
            return true;
        }

        return $request->user()->authTo(
            WarehousePermissionsEnum::getStockEditPermissionNames($this->organisation)
        );
    }

}
