<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 18 Nov 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\DropPoint;

use App\Actions\Production\MaterialRequest\MaterialsCheckAuthorisation;
use Lorisleiva\Actions\ActionRequest;

/**
 * Drop points are set by the production manager and the supervisor.
 */
trait WithDropPointAuthorisation
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return MaterialsCheckAuthorisation::canManageMaterialsCheck($request->user(), $this->production);
    }
}
