<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 17 Sep 2026 15:00:00 Central European Summer Time, Trnava, Slovakia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits;

use Lorisleiva\Actions\ActionRequest;

trait WithRetinaCustomerOwnedRouteModels
{
    use WithRetinaRouteModelOwnershipCheck;

    public function authorize(ActionRequest $request): bool
    {
        return $this->retinaCustomerOwnsRouteModels($request);
    }
}
