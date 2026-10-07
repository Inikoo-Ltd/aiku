<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 07 Oct 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Authorisations;

use Lorisleiva\Actions\ActionRequest;

trait WithSupplierProductImageEditAuthorisation
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if (isset($this->organisation)) {
            return $request->user()->authTo("procurement.{$this->organisation->id}.edit");
        }

        return $request->user()->authTo(['supply-chain.edit', 'goods.edit']);
    }
}
