<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 27 Apr 2025 11:33:43 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Authorisations;

use Lorisleiva\Actions\ActionRequest;

trait WithMastersAuthorisation
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $this->canEdit = $request->user()->authTo("masters.edit");
        $this->canEditPrices = $request->user()->authTo("masters.price_edit");
        $this->canEditOffers = $request->user()->authTo("masters.offer_edit");

        return $request->user()->authTo("masters.view");
    }
}
