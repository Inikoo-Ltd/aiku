<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 25 Feb 2024 10:01:43 Central Standard Time, Mexico City, Mexico
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Authorisations;

use Lorisleiva\Actions\ActionRequest;

trait WithFulfilmentShopAuthorisation
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $this->isSupervisor = $request->user()->authTo([
            "supervisor-fulfilment-shop.".$this->fulfilment->id
        ]);

        $this->canEdit = $request->user()->authTo([
            "fulfilment-shop.{$this->fulfilment->id}.edit",
            "supervisor-fulfilment-shop.".$this->fulfilment->id
        ]);

        $this->canDelete = $this->canEdit;

        return $request->user()->authTo([
            "fulfilment-shop.{$this->fulfilment->id}.view",
            "supervisor-fulfilment-shop.".$this->fulfilment->id,
            "accounting.{$this->organisation->id}.view",
            "group-webmaster.view"
        ]);
    }
}
