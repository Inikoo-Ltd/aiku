<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Wed, 30 Sep 2026 Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Authorisations;

use Lorisleiva\Actions\ActionRequest;

trait WithTradeUnitMediaEditAuthorisation
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        return $this->canEditTradeUnitMedia($request);
    }

    protected function canEditTradeUnitMedia(ActionRequest $request): bool
    {
        return $request->user()->authTo(['goods.edit', 'masters.edit', 'group-webmaster.media-edit']);
    }
}
