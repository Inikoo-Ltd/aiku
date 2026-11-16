<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 18 Oct 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Authorisations;

use App\Enums\SysAdmin\Authorisation\WarehousePermissionsEnum;
use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;

trait WithOrganisationDispatchingViewAuthorisation
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return self::canViewOrganisationDispatching($request->user(), $this->organisation);
    }

    public static function canViewOrganisationDispatching(User $user, Organisation $organisation): bool
    {
        return $user->authTo(WarehousePermissionsEnum::getDispatchingViewPermissionNames($organisation));
    }
}
