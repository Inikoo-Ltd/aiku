<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 03 Nov 2026 18:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Authorisations;

use App\Models\SysAdmin\Organisation;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;

/**
 * HR changes clockings. The accountant and the production manager (accounting edit) reach them too, but ChangeArtisanClocking
 * lets them change only artisans' weeks already locked for payroll, the ones HR no longer can.
 */
trait WithArtisanClockingEditAuthorisation
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return static::canEditClockings($request->user(), $this->organisation);
    }

    public static function canEditClockings(User $user, Organisation $organisation): bool
    {
        return $user->authTo([
            "human-resources.{$organisation->id}.edit",
            "org-supervisor.{$organisation->id}.human-resources",
            "accounting.{$organisation->id}.edit",
        ]);
    }
}
