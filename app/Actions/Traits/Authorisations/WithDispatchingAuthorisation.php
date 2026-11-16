<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 12 Mar 2025 22:05:28 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Authorisations;

use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;

trait WithDispatchingAuthorisation
{
    public function authorize(ActionRequest $request): bool
    {

        if ($this->asAction) {
            return true;
        }

        return self::canViewDispatching($request->user(), $this->warehouse);

    }

    public static function canViewDispatching(User $user, Warehouse $warehouse): bool
    {
        return $user->authTo(["dispatching.$warehouse->id.view", "fulfilment.$warehouse->id.view"]);
    }
}
