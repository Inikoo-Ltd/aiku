<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 15 Oct 2026 Malaga, Spain
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Production\MaterialRequest;

use App\Actions\SysAdmin\User\GetUserCurrentEmployee;
use App\Enums\Production\ProductionMaterialRequest\ProductionMaterialRequestStateEnum;
use App\Models\Production\Production;
use App\Models\Production\ProductionMaterialRequest;
use App\Models\SysAdmin\User;

final class MaterialsCheckAuthorisation
{
    /**
     * The production manager and the supervisor run the morning check and choose who else does.
     */
    public static function canManageMaterialsCheck(User $user, Production $production): bool
    {
        return $user->authTo([
            'org-supervisor.'.$production->organisation_id,
            "productions_operations.$production->id.orchestrate",
        ]);
    }

    /**
     * Anyone working in the production asks the warehouse for materials, as anyone could write on the sheet it replaces.
     */
    public static function canRequestMaterials(User $user, Production $production): bool
    {
        return $user->authTo([
            'org-supervisor.'.$production->organisation_id,
            'productions-view.'.$production->organisation_id,
            "productions_operations.$production->id.view",
            "productions_operations.$production->id.edit",
            "productions_operations.$production->id.orchestrate",
        ]);
    }

    public static function checkingRequest(Production $production, string $date): ?ProductionMaterialRequest
    {
        return ProductionMaterialRequest::where('production_id', $production->id)
            ->whereDate('plan_date', $date)
            ->where('state', ProductionMaterialRequestStateEnum::CHECKING)
            ->first();
    }

    public static function canDoMaterialsCheck(User $user, Production $production, string $date): bool
    {
        if (self::canManageMaterialsCheck($user, $production)) {
            return true;
        }

        $checkerId = self::checkingRequest($production, $date)?->checker_employee_id;

        return $checkerId && GetUserCurrentEmployee::run($user, $production->organisation_id)?->id === $checkerId;
    }
}
