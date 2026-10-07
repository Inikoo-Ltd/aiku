<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 Oct 2026 12:00:00 British Summer Time, Sheffield, UK
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Authorisations;

use App\Actions\Inventory\WarehouseTeam\UI\ShowWarehouseTeam;
use App\Models\HumanResources\Clocking;
use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\ActionRequest;

/**
 * The warehouse team section is for the people who run the warehouse floor: any warehouse
 * supervisor of this warehouse, or someone who can edit HR in its organisation.
 */
trait WithWarehouseTeamAuthorisation
{
    public static function canManageWarehouseTeam(User $user, Warehouse $warehouse): bool
    {
        return $user->authTo([
            "supervisor-dispatching.$warehouse->id",
            "supervisor-incoming.$warehouse->id",
            "supervisor-stocks.$warehouse->id",
            "supervisor-locations.$warehouse->id",
            "human-resources.$warehouse->organisation_id.edit",
        ]);
    }

    public static function ensureTeamClocking(Warehouse $warehouse, Clocking $clocking): void
    {
        if ($clocking->subject_type !== 'Employee' || !ShowWarehouseTeam::teamQuery($warehouse)->whereKey($clocking->subject_id)->exists()) {
            throw ValidationException::withMessages(['clocking' => __('This clocking is not from the warehouse team.')]);
        }
    }

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return self::canManageWarehouseTeam($request->user(), $this->warehouse);
    }
}
