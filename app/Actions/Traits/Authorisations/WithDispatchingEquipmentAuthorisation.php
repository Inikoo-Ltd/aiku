<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 12 Oct 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Authorisations;

use App\Models\Inventory\Warehouse;
use App\Models\SysAdmin\User;
use Lorisleiva\Actions\ActionRequest;

trait WithDispatchingEquipmentAuthorisation
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $this->canEdit = $request->user()->authTo([
            "supervisor-locations.{$this->warehouse->id}",
            "locations.{$this->warehouse->id}.edit",
        ]);
        $this->canDelete = $this->canEdit;

        return self::canViewDispatchingEquipment($request->user(), $this->warehouse);
    }

    public static function canViewDispatchingEquipment(User $user, Warehouse $warehouse): bool
    {
        return $user->authTo([
            "org-admin.$warehouse->organisation_id",
            "dispatching.$warehouse->id.view",
            "fulfilment.$warehouse->id.view",
            "supervisor-locations.$warehouse->id",
            "locations.$warehouse->id.view",
        ]);
    }
}
