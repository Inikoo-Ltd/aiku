<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Mon, 10 Jun 2024 11:44:14 Central European Summer Time, Plane Abu Dhabi - Kuala Lumpur
 * Copyright (c) 2024, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Authorisations;

use Lorisleiva\Actions\ActionRequest;

trait WithCatalogueAuthorisation
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        if ($request->route()->getName() == 'grp.org.shops.index') {
            $this->canEdit = $request->user()->authTo(
                [
                    'org-supervisor.'.$this->organisation->id,
                ]
            );

            return $request->user()->authTo(
                [
                    'org-supervisor.'.$this->organisation->id,
                    'shops-view'.$this->organisation->id,
                ]
            );
        } else {
            $this->canEdit = $request->user()->authTo("products.{$this->shop->id}.edit");

            return $request->user()->authTo("products.{$this->shop->id}.view");
        }
    }
}
