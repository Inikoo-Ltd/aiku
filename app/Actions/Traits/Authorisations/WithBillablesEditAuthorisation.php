<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Tue, 30 Sep 2026, Sanur, Bali, Indonesia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Authorisations;

use Lorisleiva\Actions\ActionRequest;

trait WithBillablesEditAuthorisation
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $this->canEditBillables($request);
    }

    protected function canEditBillables(ActionRequest $request): bool
    {
        return $request->user()->authTo(
            [
                "products.{$this->shop->id}.edit",
                "accounting.{$this->shop->organisation_id}.edit"
            ]
        );
    }
}
