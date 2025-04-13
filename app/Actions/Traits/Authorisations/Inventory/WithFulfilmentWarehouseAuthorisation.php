<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 25 Feb 2024 10:01:43 Central Standard Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Authorisations\Inventory;

use Lorisleiva\Actions\ActionRequest;

trait WithFulfilmentWarehouseAuthorisation
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $this->canEdit = $request->user()->authTo([
            "fulfilment.{$this->warehouse->id}.edit",
            "supervisor-incoming.".$this->warehouse->id,
            "supervisor-fulfilment.".$this->warehouse->id
        ]);

        $this->canDelete = $this->canEdit;

        return $request->user()->authTo([
            "fulfilment.{$this->warehouse->id}.view",
            "supervisor-incoming.".$this->warehouse->id,
            "supervisor-fulfilment.".$this->warehouse->id,
            'warehouses-view.'.$this->organisation->id,
        ]);
    }
}
