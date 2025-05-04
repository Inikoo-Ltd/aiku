<?php

/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Sun, 04 May 2025 23:11:35 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2025, Raul A Perusquia Flores
 */

namespace App\Actions\Traits\Authorisations\Ordering;

use Lorisleiva\Actions\ActionRequest;

trait WithOrderingEditAuthorisation
{
    public function authorize(ActionRequest $request): bool
    {
        if ($this->asAction) {
            return true;
        }

        $routeName = $request->route()->getName();

        if (str_starts_with($routeName, 'grp.org.shops.show.crm..')) {
            return $request->user()->authTo(["orders.{$this->shop->id}.edit", "crm.{$this->shop->id}.edit",]);
        }


        return $request->user()->authTo(["orders.{$this->shop->id}.edit"]);
    }
}
