<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 03 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\WorkArea;

use App\Models\Production\Production;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;

/**
 * Work areas are set by the Production Manager (an organisation admin).
 */
trait WithWorkAreaAuthorisation
{
    public static function canManageWorkAreas(User $user, Production $production): bool
    {
        return $user->authTo('org-supervisor.'.$production->organisation_id);
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return self::canManageWorkAreas($request->user(), $this->production);
    }
}
