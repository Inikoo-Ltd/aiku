<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 05 Aug 2024 15:18:19 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Authorisations\Inventory;

use Lorisleiva\Actions\ActionRequest;

trait WithInventoryAuthorisation
{
    public function authorize(ActionRequest $request): bool
    {
        if (str_starts_with($request->route()->getName(), 'grp.overview')) {
            return $request->user()->authTo("group-overview");
        }

        $this->canEdit = $request->user()->authTo("inventory.{$this->organisation->id}.edit");

        return $request->user()->authTo("inventory.{$this->organisation->id}.view");
    }
}
