<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Fri, 25 Sep 2026 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Authorisations\Inventory;

use Lorisleiva\Actions\ActionRequest;

trait WithReturnsAuthorisation
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if (isset($this->warehouse)) {
            $incomingPermission = $request->isMethod('GET') ? 'view' : 'edit';

            return $request->user()->authTo([
                "incoming.{$this->warehouse->id}.$incomingPermission",
                "returns.{$this->warehouse->id}",
            ]);
        }

        return $request->user()->authTo([
            "orders.{$this->shop->id}.view",
            "crm.{$this->shop->id}.view",
        ]);
    }
}
