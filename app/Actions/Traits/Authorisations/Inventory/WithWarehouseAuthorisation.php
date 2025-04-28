<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 13 Apr 2025 21:22:35 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Authorisations\Inventory;

use Lorisleiva\Actions\ActionRequest;

trait WithWarehouseAuthorisation
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $this->canEdit = $request->user()->authTo([
            "supervisor-locations.".$this->warehouse->id,
            'locations.'.$this->warehouse->id.'.edit',
        ]);

        $this->canDelete = $this->canEdit;

        return $request->user()->authTo([
            "supervisor-incoming.".$this->warehouse->id,
            "supervisor-locations.".$this->warehouse->id,
            'locations.'.$this->warehouse->id.'.view',
            'warehouses-view.'.$this->organisation->id,
        ]);
    }
}
