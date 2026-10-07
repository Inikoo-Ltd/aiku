<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Sep 2026, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Authorisations;

use Lorisleiva\Actions\ActionRequest;

trait WithBillablesAuthorisation
{
    use WithBillablesEditAuthorisation;

    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $this->canEdit   = $this->canEditBillables($request);
        $this->canDelete = $this->canEdit;

        return $request->user()->authTo(
            [
                "products.{$this->shop->id}.view",
                "web.{$this->shop->id}.view",
                "group-webmaster.view",
                "accounting.{$this->shop->organisation_id}.view"
            ]
        );
    }
}
