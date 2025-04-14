<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 14 Apr 2025 00:03:47 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Authorisations\Inventory;

use Lorisleiva\Actions\ActionRequest;

trait WithFulfilmentWarehouseEditAuthorisation
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }


        return $request->user()->authTo([
            "fulfilment.{$this->warehouse->id}.edit",
            "supervisor-incoming.".$this->warehouse->id,
            "supervisor-fulfilment.".$this->warehouse->id
        ]);
    }
}
